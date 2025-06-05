<?php

namespace app\model;

use support\Model;

/**
 * tg_keywords 
 * @property integer $id (主键)
 * @property string $keyword_hash MD5哈希，避免重复存储
 * @property string $keyword_text 原始关键词文本
 * @property string $created_at
 */
class TgKeywords extends Model
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
    protected $table = 'tg_keywords';

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
        'keyword_hash',
        'keyword_text',
        'created_at'
    ];
    
    
}
