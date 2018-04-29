<?php

namespace Kommercio\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Glide\ServerFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use League\Glide\Signatures\SignatureFactory;
use Kommercio\Models\Media;

class ImageController
{
    protected $app;
    protected $request;
    protected $glideConfig;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->glideConfig = config('laravel-glide');
        $this->request = $this->app['request'];
    }

    protected function validateSignature()
    {
        foreach ($this->request->all() as $parameter => $value) {
            if (empty($value) === true) {
                $this->request->query->remove($parameter);
            }
        }

        if ($this->glideConfig['useSecureURLs']) {
            SignatureFactory::create($this->app['config']->get('app.key'))
                ->validateRequest($this->request);
        }
    }

    public function style($style, $image)
    {
        $this->validateSignature();

        $this->writeIgnoreFile();

        //Previouse version use Local storage
        //$server = $this->setGlideServer($this->setImageSource(), $this->setImageCache(), $api);

        //Update to storage based on image
        $file = Cache::remember(md5($image), 1440, function() use ($image) {
            return Media::whereRaw('CONCAT(folder, filename) LIKE ?', [$image])->firstOrFail();
        });

        $server = $this->setGlideServer($this->setImageSource($file->storage), $this->setImageCache($style), $style);

        try{
            $imageOutput = $server->outputImage($this->request->path(), $this->getPresets($style));
        }catch(\Exception $e){
            abort($e->getCode(), $e->getMessage());
        }

        return $imageOutput;
    }

    protected function getPresets($style)
    {
        $styles = array_merge(config('kommercio.image_styles'), config('project.image_styles', []));

        return $styles[$style];
    }

    protected function setGlideServer($source, $cache, $style)
    {
        $imagePath = config('kommercio.images_path');

        $server = ServerFactory::create([
            'base_url' => $imagePath.'/'.$style,
            'source' => $source,
            'cache' => $cache
        ]);

        $server->setBaseUrl($imagePath.'/'.$style);

        return $server;
    }

    /**
     *  Set the source path for images
     *
     * @return Filesystem
     */
    protected function setImageSource($source='local')
    {
        return Storage::disk($source)->getDriver();
    }

    /**
     * Set the cache folder
     *
     * @return Filesystem
     */
    protected function setImageCache($path='')
    {
        return (new Filesystem(new Local(
            $this->glideConfig['cache']['path'].'/'.$path
        )));
    }

    /**
     * Copy the gitignore stub to the given directory.
     */
    protected function writeIgnoreFile()
    {
        $this->createCacheFolder();

        $destinationFile = $this->glideConfig['cache']['path'].'/.gitignore';

        if (!file_exists($destinationFile)) {
            $this->app['files']->copy(__DIR__.'/../../../stubs/gitignore.txt', $destinationFile);
        }
    }

    private function createCacheFolder()
    {
        if (!is_dir($this->glideConfig['cache']['path'])) {
            mkdir($this->glideConfig['cache']['path'], 0755, true);
        }
    }
}
