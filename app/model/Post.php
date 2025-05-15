<?php

namespace app\model;

use Illuminate\Database\Eloquent\Casts\Json;
use support\Model;

/**
 * post 
 * @property integer $id (主键)
 * @property string $title 
 * @property string $desc 
 * @property string $category 
 * @property string $creator 
 * @property string $pub_date 
 * @property string $created_at 
 * @property string $updated_at 
 * @property string $from_type
 * @property int $is_token
 * @property int $is_search
 * @property Json $tokens
 */
class Post extends Model
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
    protected $table = 'post';

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
    
    
}
