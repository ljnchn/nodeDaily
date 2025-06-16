<?php

namespace app\model;

use support\Model;

/**
 * tg_post 
 * @property integer $id (主键)
 * @property integer $pid 
 * @property string $title 
 * @property string $desc 
 * @property integer $from_type 
 * @property string $created_at 
 * @property string $updated_at
 */
class TgPost extends Model
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
    protected $table = 'tg_post';

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
        'pid',
        'title',
        'desc',
        'from_type'
    ];
}
