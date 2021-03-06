<?php

use App\Category;
use App\Comment;
use App\Post;
use App\Task;
use App\User;
use Facades\Tests\Setup\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create 5 posts
        factory(Post::class, 125)->create()->each(function (Post $post) {
            $post->tasks()->createMany(
                factory(Task::class, rand(3, 7))->raw([
                    'userId' => UserFactory::create()->id
                ])
            );

            // $ctgIds = factory(Category::class, rand(2, 5))
            //     ->create()
            //     ->pluck('id');
            
            // $post->categories()->attach($ctgIds);

            // with comments
            $post->comments()->createMany(
                factory(Comment::class, rand(2, 4))->raw([
                    'userId' => UserFactory::create()->id
                ])
            );
        });
    }
}
