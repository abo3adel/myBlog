<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Post;
use App\User;
use Faker\Generator as Faker;
use Illuminate\Support\Arr;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence(3),
        'body' => $faker->text(350),
        'img' => Arr::random([1, 2, 3, 4, 5]) . '.png',
        'user_id' => function () {
            return factory(User::class)->create()->id;
        }
    ];
});
