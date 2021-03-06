<?php

namespace Tests\Feature;

use App\Category;
use App\Http\Controllers\UploadImage;
use App\Post;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Facades\Tests\Setup\PostFactory;
use Facades\Tests\Setup\UserFactory;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function testAnyUserCanViewAllPosts()
    {
        $post = PostFactory::create();

        $this->assertDatabaseHas('posts', $post->toArray());

        $this->get('/posts')
            ->assertOk()
            ->assertSee($post->title)
            ->assertSee($post->categories->first());
    }

    public function testPostCanBeViewdBySlug()
    {
        $this->canSeePost(PostFactory::ownedBy($this->signIn())->create());
    }

    public function testGuestCannotCreatePost()
    {
        $this->get('/posts/create')->assertRedirect('login');

        $this->post('/posts', [])->assertRedirect('login');
    }

    public function testAuthrizedUserCanCreatePost()
    {
        $this->withoutExceptionHandling();

        $post = PostFactory::ownedBy($this->signIn())->create('raw');

        $this->get('/posts/create')
            ->assertStatus(200)
            ->assertViewIs('post.create');
        
        Arr::forget($post, 'img');

        $this->post('/posts', $post)
            ->assertRedirect('/posts/' . $post['slug']);
        
        $post = Post::latest()->first();

        $this->canSeePost($post);
    }

    public function testUserCanUpdateImageWithPost()
    {
        $post = PostFactory::ownedBy($this->signIn())->create('raw');

        Storage::fake('local');

        $file = UploadedFile::fake()->image('name.png');

        $post['img'] = $file;

        $this->post(
            '/posts',
            $post,
            $this->setReferer('/posts/create')
        )->assertRedirect('/posts/' . $post['slug']);

        Storage::disk('local')
            ->assertExists(
                UploadImage::IMAGE_URI . '/' . $file->hashName()
            );
    }

    public function testGuestCannotUpdatePost()
    {
        $this->guestUpdateOrDeletePost('patch');
    }

    public function testGuestCannotDeletePost()
    {
        $this->guestUpdateOrDeletePost('delete');
    }

    public function testUserWithoutPermissionCannotUpdateOrDeletePost()
    {
        $post = PostFactory::ownedBy(UserFactory::create())->create();

        $this->actingAs(UserFactory::create())
            ->patch($post->path(), $post->attributesToArray())
            ->assertStatus(403);
        
        $this->actingAs(UserFactory::create())
            ->delete($post->path())
            ->assertStatus(403);
    }

    public function testPostOwnerCanUpdatePostWithoutPermission()
    {
        $post = PostFactory::ownedBy($this->signIn())->create();

        $this->patch(
            $post->path(),
            $post->attributesToArray(),
            $this->setReferer($post->path())
            )->assertRedirect($post->path());
        
        $this->assertDatabaseHas('posts', $post->toArray());
    }

    public function testPostOwnerCanDeletePostWithoutPermission()
    {
        $post = PostFactory::ownedBy($this->signIn())->create();

        $this->delete(
            $post->path(),
            $post->attributesToArray(),
            $this->setReferer($post->path())
        )->assertRedirect('/posts');
        
        $this->assertDatabaseMissing('posts', $post->toArray());
    }

    public function testUserWithPermissionCanUpdateOtheresPost()
    {
        $this->withoutExceptionHandling();

        [$user, $post] = $this->userUpdateOrDeletePost('update');

        $post->title = $this->faker->unique()->sentence;
        
        $post->img = null;
    
        // try to update post
        $res = $this->actingAs($user)
            ->patch(
                $post->path(),
                $post->attributesToArray(),
                $this->setReferer($post->path())
            );
        
        // update post slug
        $post->slug = Str::slug($post->title);

        // assert it is being redirect to new slug
        $res->assertRedirect($post->path());
        
        $this->assertDatabaseHas(
            'posts',
            $post->only('title', 'slug')
        );

        $this->canSeePost($post);
    }

    public function testUserWithPermissionCanDeleteOtheresPost()
    {
        [$user, $post] = $this->userUpdateOrDeletePost('delete');

        // try to update post
        $this->actingAs($user)
            ->delete($post->path())
            ->assertRedirect('/posts');
        
        $this->assertDatabaseMissing('posts', $post->toArray());
    }

    public function testUserCanSearchForPosts()
    {
        // create 20 posts with random users
        PostFactory::create('create', 20);
    
        $post = Post::all()->nth(5)->last();

        // hit endpoint for search
        $this->get(
            '/posts/q/' . Str::limit(urlencode($post->title), 5, '')
            )->assertOk()
            ->assertSee($post->title);
    }

    public function testUserCanViewPostAndCategories() : void
    {
       $this->signIn();

        $post = PostFactory::create();

        $ctgIds = factory(Category::class, rand(2, 5))
            ->create()
            ->pluck('id');

        $post->categories()->attach($ctgIds);

        $this->canSeePost($post)
            ->assertSee($post->categories->last()->title);
    }

    public function testUnAuthrizedUserCannotInviteNewUsers()
    {
        $post = PostFactory::ownedBy($user = $this->signIn())
            ->create();
        
        $anotherUser = factory(User::class)->create();

        $this->actingAs($anotherUser)
            ->post(
                $post->path() . '/invite',
                ['userEmail' => $user->email]
            )->assertStatus(403);
    }

    public function testPostMemberCanUpdatePost()
    {
        $this->withoutExceptionHandling();

        $post = PostFactory::ownedBy($user = $this->signIn())
            ->create();

        $anotherUser = factory(User::class)->create();

        // invite new user for post members
        $this->post(
                $post->path() . '/invite',
                ['userEmail' => $anotherUser->email],
                $this->setReferer($post->path())
            )->assertRedirect($post->path());

        $this->actingAs($anotherUser)
            ->patch(
                $post->path(), [
                    'title' => $post->title,
                    'body' => $post->body
                ],
                $this->setReferer($post->path())
        )->assertRedirect($post->path());
        
        $this->assertTrue(
            $post->members->contains($anotherUser)
        );

        $this->assertDatabaseHas(
            'post_members', [
                'userId' => $anotherUser->id,
                'postId' => $post->id
            ]
        );
    }

    public function testNewMemberMustBeRegisterdAtThisSite()
    {
        $post = PostFactory::ownedBy($user1 = $this->signIn())
            ->create();
        
        $user2 = UserFactory::create('make');

        $this->post(
            $post->path() . '/invite',
            ['userEmail' => $user2->email],
            $this->setReferer($post->path())
        )->assertRedirect($post->path())
        ->assertSessionHasErrors('userEmail', null, 'addUser');
    }

    /**
     * visit post page and assert all post debendcies can be seen
     *
     * @param Post $post
     * @return void
     */
    protected function canSeePost(Post $post) : TestResponse
    {
        return $this->get($post->path())
            ->assertOk()
            ->assertViewIs('post.show')
            ->assertSee($post->title)
            ->assertSee($post->body)
            ->assertSee($post->owner->name)
            ->assertSee($post->updated_at->diffForHumans());
    }

    public function testUserWithPermissionCanAddPostCategory()
    {
        $admin = UserFactory::admin();

        $post = PostFactory::ownedBy($admin)->create();

        $user = UserFactory::create();

        $admin->givePermTo($user, User::ADD_CATEGORIES);

        // create category
        $category = factory(Category::class)->create();

        // assert user withput permission cannot add category
        $this->actingAs(UserFactory::create())
            ->post(
                $post->path() . '/addCategory',
                ['catId' => $category->id]
            )->assertStatus(403);

        // assert that user with permission can
        $this->actingAs($user)
            ->post(
                $post->path() . '/addCategory',
                ['catId' => $category->id],
                $this->setReferer($post->path())
            )->assertRedirect($post->path());

        $this->get($post->path())
            ->assertSee($category->title);
    }

    /**
     * Act as guest and try to update or delete post
     *
     * @param string $method
     * @return void
     */
    protected function guestUpdateOrDeletePost(string $method) : void
    {
        $post = PostFactory::create('raw');

        $this->get('/posts/edit')->assertRedirect('login');

        $this->{$method}('/posts/' . $post['slug'], $post)->assertRedirect('login');

        $this->assertDatabaseMissing('posts', $post);
    }

    /**
     * create user and give him permission to delete other users posts
     *
     * @param string $method
     * @return array [\User, \Post]
     */
    protected function userUpdateOrDeletePost(string $method) : array
    {
        [$admin, $user] = UserFactory::createWithAdmin();

        // give user permission to update post
        $admin->givePermTo($user, User::DELETE_POSTS);

        // create post with another user
        $post = PostFactory::ownedBy(UserFactory::create())->create();

        return [$user, $post];
    }
}