<?php

namespace Kommercio\Models;

use Illuminate\Database\Eloquent\Model;
use Kommercio\Models\Interfaces\AuthorSignatureInterface;
use Kommercio\Traits\Model\AuthorSignature;

class Log extends Model implements AuthorSignatureInterface
{
    use AuthorSignature;

    protected $fillable = ['tag', 'notes', 'author'];

    //Relations
    public function loggable()
    {
        return $this->morphTo();
    }

    //Statics
    public static function log($tag, $message, Model $model, $userName = null)
    {
        $log = new self();
        $log->fill([
            'tag' => $tag,
            'notes' => $message,
        ]);

        $log->loggable()->associate($model);
        $log->save();

        $log->author = $userName?:($log->createdBy->fullName?:$log->createdBy->email);
        $log->save();

        return $log;
    }
}
