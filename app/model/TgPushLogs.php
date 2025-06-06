<?php

namespace app\model;

use support\Model;

/**
 * tg_push_logs 
 * @property integer $id (主键)
 * @property integer $user_id 
 * @property integer $chat_id 
 * @property integer $post_id 
 * @property integer $sub_id 
 * @property integer $push_status 
 * @property string $error_message 
 * @property string $created_at
 */
class TgPushLogs extends Model
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
    protected $table = 'tg_push_logs';

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
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'chat_id',
        'post_id',
        'sub_id',
        'push_status',
        'error_message',
        'created_at'
    ];
    
}
