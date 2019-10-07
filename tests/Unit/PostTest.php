<?php

namespace Tests\Unit;

use App\Category;
use App\Post;
use Facades\Tests\Setup\PostFactory;
use Facades\Tests\Setup\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;
    
    public function testItHasMiniBody()
    {
        $post = factory(Post::class)->make();

        $this->assertIsString($post->mini_body);

        $this->assertLessThanOrEqual(250, strlen($post->mini_body));
    }

    public function testItHasSlug()
    {
        $post = factory(Post::class)->make();

        $this->assertIsString($post->slug);

        $this->assertEquals(Str::slug($post->title), $post->slug);
    }

    public function testItHasPath()
    {
        $post = factory(Post::class)->make();

        $this->assertIsString($post->path());

        $this->assertEquals('/posts/' . $post->slug, $post->path());
    }

    public function testItHasOwner()
    {
        $user = $this->signIn();

        $post = PostFactory::ownedBy($user)->create();

        $this->assertEquals(
            $post->owner->toArray(),
            $user->toArray()
        );
    }

    public function testItHasCategories()
    {
        $post = factory(Post::class)->create();
        $ctg = factory(Category::class, 2)->create()->last();
        
        $post->categories()->attach($ctg->id);
        
        $this->assertIsIterable($post->categories);

        $this->assertCount(1, $post->categories);

        $this->assertDatabaseHas('post_category', [
            'post_id' => $post->id,
            'category_id' => $ctg->id
        ]);
    }

    public function testItHasMembers()
    {
        $post = PostFactory::create();

        $this->assertIsIterable($post->members);
    }

    public function testItCanInviteNewUsers()
    {
        $post = PostFactory::create();

        $post->invite($user = UserFactory::create());

        $this->assertCount(1, $post->members);

        $this->assertDatabaseHas('post_members', [
            'postId' => $post->id,
            'userId' => $user->id
        ]);
    }

    public function testItHasActivity()
    {
        $post = PostFactory::create();

        $this->assertIsIterable($post->activity);
    }

    public function testItHasActivities()
    {
        $post = PostFactory::create();

        $this->assertIsIterable($post->activities);
    }
}
