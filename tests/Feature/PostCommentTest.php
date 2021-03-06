<?php

namespace Tests\Feature;

use App\Comment;
use Facades\Tests\Setup\PostFactory;
use Facades\Tests\Setup\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PostCommentTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    public function testGuestCannotCreateComment()
    {
        $post = PostFactory::create();

        $this->post(
            $post->path() . '/comments',
            factory(Comment::class)->raw()
        )->assertRedirect('login');
    }

    public function testUserCanCreateComment()
    {
        [$post, $comment] = PostFactory::withComments()
            ->ownedBy($user = $this->signIn())
            ->createBoth('make');

        $this->post(
            $post->path() . '/comments',
            $comment->only('body'),
            $this->setReferer($post->path())
        )->assertRedirect($post->path());

        $this->assertDatabaseHas('comments', $comment->toArray());

        $this->get($post->path())
            ->assertSee($comment->body)
            ->assertSee($comment->owner->name);
    }

    public function testCommentRequiresBody()
    {
        $post = PostFactory::ownedBy($this->signIn())->create();

        $this->post($post->path() . '/comments', [])
            ->assertSessionHasErrors();
    }

    public function testUserCanReplayComment()
    {
        $this->withoutExceptionHandling();

        [$post, $comment] = PostFactory::withComments()
            ->createBoth();

        $replayComment = factory(Comment::class)->make();

        $this->actingAs(UserFactory::create())
            ->post(
                $comment->path(),
                $replayComment->only('body'),
                $this->setReferer($post->path())
            )->assertRedirect($post->path());
        
        $this->assertDatabaseHas(
            'comment_replays',
            $replayComment->only('body')
        );

        $this->get($post->path())->assertSee($replayComment->body);
    }

    public function testOnlyAdminCanDeleteComments()
    {
        [$post, $comment] = PostFactory::withComments()
            ->createBoth();

        // try as random user  
        $this->actingAs(UserFactory::create())
            ->delete($comment->path())->assertStatus(403);

        // try with admin user
        $this->actingAs(UserFactory::admin())
            ->delete(
                $comment->path(),
                [],
                $this->setReferer($post->path())
            )->assertRedirect($post->path());

        $this->assertDatabaseMissing(
            'comments',
            $comment->attributesToArray()
        );

        $this->get($post->path())
            ->assertDontSee($comment->body);
    }
}