<?php

namespace app\model;

use support\Model;

/**
 * tg_users 
 * @property integer $id (主键)
 * @property integer $chat_id 
 * @property string $username 
 * @property string $first_name 
 * @property string $last_name 
 * @property integer $is_active 
 * @property string $created_at 
 * @property string $updated_at
 */
class TgUsers extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'mysql';
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tg_users';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chat_id',
        'username', 
        'first_name',
        'last_name',
        'is_active',
        'created_at',
        'updated_at'
    ];
    
    
}
