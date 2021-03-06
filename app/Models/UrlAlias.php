<?php

namespace Kommercio\Models;

use Illuminate\Database\Eloquent\Model;
use Kommercio\Models\Interfaces\UrlAliasInterface;

class UrlAlias extends Model
{
    protected $guarded = [];

    //Statics
    public static function saveAlias($external_path, UrlAliasInterface $model)
    {
        $existing = self::where('internal_path', $model->getInternalPathSlug().'/'.$model->id)
            ->where('locale', $model->getTranslation()->locale)->first();

        if($existing){
            $urlAlias = $existing;
        }else{
            $urlAlias = new self();
        }

        $existingExternalCount = self::where('external_path', $external_path)
            ->where('locale', $model->getTranslation()->locale)->where('internal_path', '<>', $model->getInternalPathSlug().'/'.$model->id)->count();

        if($existingExternalCount > 0){
            $external_path .= '-'.$existingExternalCount+1;
        }

        $urlAlias->internal_path = $model->getInternalPathSlug().'/'.$model->id;
        $urlAlias->external_path = $external_path;
        $urlAlias->locale = $model->getTranslation()->locale;
        $urlAlias->save();
    }

    public static function deleteAlias($internal_path, $locale = null)
    {
        $qb = self::where('internal_path', $internal_path);

        if($locale){
            $qb->where('locale', $locale);
        }

        $aliases = $qb->get();

        foreach($aliases as $alias){
            $alias->delete();
        }
    }
}
