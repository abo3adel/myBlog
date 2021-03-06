<?php declare(strict_types = 1);

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * Declare Permission constants
     * 
     * normal user => declare posts and assign categoreis to them
     * team memmber user => change teams posts
     * visor user => above with any team and add categories
     * super user =>  above and edit|remove categories
     * admin => change user access level 31
     */
    const ADD_POSTS = 1;
    const DELETE_POSTS = 2;
    const ADD_CATEGORIES = 4;
    const EDIT_CATEGORIES = 8;
    const EDIT_USER_ACCESS = 16;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * check if user can do a specific action
     *
     * @param string $perm
     * @param bool $set
     * @return boolean
     */
    public function canDo(int $userPermission) : bool
    {
        return !!($this->perm & $userPermission);
    }

    /**
     * change user permission
     *
     * @param integer $userPermission
     * @return boolean
     */
    public function givePermTo(User $user, int $userPermission) : bool
    {
        // check if user can change user access
        if (!$this->canDo(self::EDIT_USER_ACCESS)) {
            return false;
        }

        // list all permissions into one array
        $arr = [
            self::ADD_POSTS,
            self::DELETE_POSTS,
            self::ADD_CATEGORIES,
            self::EDIT_CATEGORIES,
            self::EDIT_USER_ACCESS
        ];

        /**
         * returns the sum of avaliable permissions to this user
         * be removing the permissions greate than $userPermission
         * 
         * @example $userPermission = 2 which is DELETE_POST
         *  THEN 4,8,16 will be removed
         */
        $user->perm = array_sum(
            array_filter($arr, function ($x) use ($userPermission) {
                return $x <= $userPermission;
            })
        );
        
        return $user->update();
    }

    public function getTypeAttribute() : string
    {
        if ($this->canDo(self::EDIT_USER_ACCESS)) {
            return 'admin';
        } elseif ($this->canDo(self::EDIT_CATEGORIES)) {
            return 'super';
        } elseif ($this->canDo(self::ADD_CATEGORIES)) {
            return 'visor';
        } else {
            return 'normal';
        }
    }

    public function posts() : HasMany
    {
        return $this->hasMany(Post::class, 'userId')->latest();
    }

    public function createWithSlug(array $post) {
        $post['slug'] = Str::slug($post['title']);
        
        return $this->posts()->create($post);
    }

    public function createWithTasks(array $arr)
    {
        $tasks = $arr['tasks'];
        Arr::forget($arr, 'tasks');

        if ($post = $this->createWithSlug($arr)) {
            foreach (json_decode($tasks) as $task) {
                $post->addTask($task->value);
            }
        }

        return $post;
    }
}
