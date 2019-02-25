<?php
namespace Ares333\Yaf\Tool\Uploader;

use Ares333\Yaf\Helper\Http;
use Gumlet\ImageResize;

/**
 * CREATE TABLE `image` (
 * `id` int(11) NOT NULL,
 * `width` int(11) NOT NULL DEFAULT '0',
 * `height` int(11) NOT NULL DEFAULT '0',
 * PRIMARY KEY (`id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */
class Image extends File
{

    const ERR_IMAGE_INVALID = 100;

    protected $tableImage = 'image';

    protected $compress;

    protected $resize;

    protected $htmlImagePrefix = 'img';

    protected $htmlTemplate = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"></head><body>{$body}</body></html>';

    /**
     * Image constructor.
     *
     * @param callable $getPdo
     * @param string $dir
     * @param string $url
     * @param array|string $extensions
     */
    function __construct(callable $getPdo, $dir = null, $url = null, $extensions = null)
    {
        $this->errorMessage[self::ERR_IMAGE_INVALID] = 'Not a normal image file';
        parent::__construct($getPdo, $dir, $url, $extensions);
    }

    /**
     *
     * @param number $quality
     *            0-100
     */
    function setCompress($quality)
    {
        $this->compress = $quality;
    }

    /**
     *
     * @param number $width
     * @param number $height
     * @param bool $ratio
     * @param bool $enlarge
     */
    function setResize($width, $height, $ratio = true, $enlarge = false)
    {
        $this->resize = array(
            'width' => $width,
            'height' => $height,
            'ratio' => $ratio,
            'enlarge' => $enlarge
        );
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \Ares333\Yaf\Tool\Uploader\File::doUpload()
     */
    protected function doUpload($content, $cbWrite, $ext)
    {
        if (strtolower($ext) == 'svg') {
            $imagesize = [
                0,
                0
            ];
        } else {
            if ($content['type'] === 'file') {
                $imagesize = getimagesize($content['value']);
            } else {
                $imagesize = getimagesizefromstring($content['value']);
            }
        }
        if (false === $imagesize) {
            return $this->getRes(null, self::ERR_IMAGE_INVALID);
        }
        $funcContentTrans = function ($content) {
            if ($content['type'] == 'file') {
                $content['type'] = 'content';
                $content['value'] = file_get_contents($content['value']);
            }
            return $content;
        };
        if (isset($this->resize) && in_array(strtolower($ext), [
            'gif',
            'jpg',
            'jpeg',
            'png',
            'webp'
        ])) {
            $content = $funcContentTrans($content);
            $resize = ImageResize::createFromString($content['value']);
            if ($this->resize['ratio']) {
                $resize->resizeToBestFit($this->resize['width'], $this->resize['height'], $this->resize['enlarge']);
            } else {
                $resize->resize($this->resize['width'], $this->resize['height'], $this->resize['enlarge']);
            }
            $content['value'] = $resize->getImageAsString();
        }
        if (isset($this->compress)) {
            $content = $funcContentTrans($content);
            $imagick = new \Imagick();
            $imagick->readimageblob($content['value']);
            $imagick->setImageCompressionQuality($this->compress);
            $content['value'] = $imagick->getImageBlob();
        }
        $res = parent::doUpload($content, $cbWrite, $ext);
        if ($res['code'] !== 0) {
            return $res;
        }
        $rowFile = $res['value'];
        $rowImage = new \stdClass();
        $rowImage->id = $rowFile->id;
        $rowImage->width = $imagesize[0];
        $rowImage->height = $imagesize[1];
        $pdo = $this->getPdo();
        // update width,height
        $pdo->prepare('insert into ' . $this->tableImage . ' values(?,?,?) on duplicate key update width=?,height=?')->execute(
            array(
                $rowImage->id,
                $rowImage->width,
                $rowImage->height,
                $rowImage->width,
                $rowImage->height
            ));
        $rowImage->file = $rowFile;
        $res['value'] = $rowImage;
        return $res;
    }

    /**
     *
     * @param string $table
     */
    function setTableImage($table)
    {
        $this->tableImage = $table;
    }

    /**
     *
     * @return string
     */
    function getTableImage()
    {
        return $this->tableImage;
    }

    /**
     * Mainly used for web editor
     *
     * @param string $html
     * @param bool $isFragment
     *
     * @return string
     */
    function htmlDecode($html, $isFragment = true)
    {
        if (! isset($this->url)) {
            user_error('Html decode failed because prefix url not set', E_USER_ERROR);
        }
        if (! isset($html)) {
            return;
        }
        if ($isFragment) {
            $html = str_replace('{$body}', $html, $this->htmlTemplate);
        }
        $domDoc = new \DOMDocument();
        $domDoc->loadHTML($html);
        $domImgList = $domDoc->getElementsByTagName('img');
        $map = [];
        foreach ($domImgList as $v) {
            $vSrc = $v->getAttribute('src');
            $vQuery = [];
            parse_str(parse_url($vSrc, PHP_URL_QUERY), $vQuery);
            foreach ($vQuery as $k1 => $v1) {
                $vMatch = [];
                if (preg_match("/^{$this->htmlImagePrefix}(\d+)$/", $k1, $vMatch) && '' === $v1) {
                    $map[$vMatch[1]] = $v;
                }
            }
        }
        $list = $this->getListById(array_keys($map));
        foreach ($list as $v) {
            $vDom = $map[$v->id];
            $vParseOri = parse_url($vDom->getAttribute('src'));
            $vParse = parse_url($v->url);
            $vParse = array_merge($vParseOri, $vParse);
            $vDom->setAttribute('src', Http::buildUrl($vParse));
        }
        if ($isFragment) {
            $html = implode(
                array_map([
                    $domDoc,
                    "saveHTML"
                ], iterator_to_array($domDoc->getElementsByTagName('body')[0]->childNodes)));
        } else {
            $html = $domDoc->saveHTML();
        }
        return $html;
    }
}