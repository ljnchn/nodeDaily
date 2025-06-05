<?php

namespace app\model;

use support\Model;

/**
 * tg_keywords_sub 
 * @property integer $id (主键)
 * @property integer $user_id 
 * @property string $keywords_text 
 * @property integer $type 1单个词2多个词
 * @property integer $keyword1_id 
 * @property integer $keyword2_id 
 * @property integer $keyword3_id 
 * @property integer $is_active 
 * @property string $created_at 
 * @property string $updated_at
 */
class TgKeywordsSub extends Model
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
    protected $table = 'tg_keywords_sub';

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
        'user_id',
        'keywords_text',
        'type',
        'keyword1_id',
        'keyword2_id', 
        'keyword3_id',
        'is_active',
        'created_at',
        'updated_at'
    ];
    
    
}
