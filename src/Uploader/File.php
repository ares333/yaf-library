<?php

namespace Ares333\Yaf\Uploader;

use Ares333\Yaf\Helper\File as FileHelper;
use finfo;
use PDO;
use Hoa\Mime\Mime;
use Ares333\Yaf\Helper\Http;
use stdClass;

/**
 * CREATE TABLE `file` (
 * `id` int(11) NOT NULL AUTO_INCREMENT,
 * `md5` binary(16) NOT NULL,
 * `name` varchar(255) DEFAULT NULL,
 * `path` varchar(255) NOT NULL,
 * `size` int(11) NOT NULL,
 * PRIMARY KEY (`id`),
 * UNIQUE KEY `uniq` (`md5`) USING BTREE
 * )DEFAULT CHARSET=utf8;
 */
class File
{

    const ERR_OK = 0;

    const ERR_BASEDIR = 0xf001;

    const ERR_WRITE = 0xf002;

    const ERR_NOT_BASE64 = 0xf003;

    const ERR_EXTENSION = 0xf004;

    const ERR_NO_FILE = 0xf005;

    const ERR_NO_MIME = 0xf006;

    const ERR_DB = 0xf007;

    const ERR_UNKNOWN = 0xf008;

    protected $table = 'file';

    protected $dir;

    protected $url;

    protected $getPdo;

    protected $pdo;

    protected $extensions;

    protected $autoExt = false;

    protected $maxsize;

    protected $fileInfo;

    protected $errorMessage = array(
        self::ERR_OK => null,
        self::ERR_WRITE => 'Write file failed',
        self::ERR_NOT_BASE64 => 'Not base 64 file',
        self::ERR_EXTENSION => 'File type is invalid',
        self::ERR_NO_FILE => 'No file found',
        self::ERR_NO_MIME => 'Can not parse mime type',
        self::ERR_DB => 'Database error',
        self::ERR_UNKNOWN => 'Unknown error',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk'
    );

    /**
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     *
     * @param string $dir
     */
    public function setDir($dir)
    {
        $this->dir = $dir;
    }

    /**
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     *
     * @param callable $getPdo
     * @param string $dir
     * @param string $url
     * @param array|string $extensions
     */
    function __construct(callable $getPdo, $dir = null, $url = null, $extensions = null)
    {
        $this->getPdo = $getPdo;
        if (isset($dir)) {
            if (!FileHelper::isAbsolute($dir)) {
                user_error('upload dir must be absolute', E_USER_ERROR);
            }
            $this->dir = rtrim($dir, '/');
        }
        if (isset($url)) {
            if (!preg_match('/https?:\/\//i', $url)) {
                $url = Http::getOriginUrl() . $url;
            }
            $this->url = $url;
        }
        $this->fileInfo = new finfo(FILEINFO_MIME_TYPE);
        if (isset($extensions)) {
            $this->extensions = $extensions;
        }
    }

    /**
     *
     * @param array|string $extensions
     */
    function setExtensions($extensions = null)
    {
        if (is_string($extensions)) {
            $extensions = [
                $extensions
            ];
        }
        foreach ($extensions as $k => $v) {
            $extensions[$k] = strtolower($v);
        }
        $this->extensions = $extensions;
    }

    /**
     *
     * @param string $table
     */
    function setTable($table)
    {
        $this->table = $table;
    }

    /**
     *
     * @return string
     */
    function getTable()
    {
        return $this->table;
    }

    /**
     *
     * @param int $size
     *            bytes
     */
    function setMaxsize($size)
    {
        $this->maxsize = $size;
    }

    /**
     *
     * @return PDO
     */
    function getPdo()
    {
        if (!isset($this->pdo)) {
            $this->pdo = call_user_func($this->getPdo);
            if (!($this->pdo instanceof PDO)) {
                user_error('pdo is invalid', E_USER_ERROR);
            }
        }
        return $this->pdo;
    }

    /**
     *
     * @return string
     */
    function getDir()
    {
        return $this->dir;
    }

    /**
     *
     * @param number $value
     * @param number $code
     * @param string $extMessage
     *
     * @return array
     */
    protected function getRes($value = null, $code = null, $extMessage = null)
    {
        if (!isset($code)) {
            $code = self::ERR_UNKNOWN;
        }
        settype($code, 'integer');
        return array(
            'code' => $code,
            'value' => $value,
            'message' => $this->errorMessage[$code] . $extMessage
        );
    }

    /**
     * upload multi $_FILES
     *
     * @param string $name
     * @param array|null $files
     * @return array item:array('value'=>null,'code'=>0,'message'=>'');
     */
    function uploadPostAll($name, $files = null)
    {
        if (!isset($files)) {
            $files = $_FILES;
        }

        if (empty($files[$name])) {
            return $this->getRes(null, self::ERR_NO_FILE);
        }
        $files = $files[$name];
        $res = array();
        if (!empty($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $k => $v) {
                $file = array();
                $file['name'] = $v;
                $file['type'] = $files['type'][$k];
                $file['tmp_name'] = $files['tmp_name'][$k];
                $file['error'] = $files['error'][$k];
                $file['size'] = $files['size'][$k];
                $res[$k] = $this->doUploadPost($file);
            }
        }
        return $res;
    }

    /**
     * upload base64 file
     *
     * @param string $content
     *
     * @return array array('value'=>null,'code'=>0,'message'=>'');
     */
    function uploadBase64($content)
    {
        if (!is_string($content)) {
            return $this->getRes(null, self::ERR_NOT_BASE64);
        }
        $pos1 = strpos($content, ':');
        $pos2 = strpos($content, ';', $pos1);
        $pos3 = strpos($content, ',', $pos2);
        $base64 = substr($content, $pos2 + 1, $pos3 - $pos2);
        if (strtolower($base64) !== 'base64,') {
            return $this->getRes(null, self::ERR_NOT_BASE64);
        }
        $content = base64_decode(str_replace(' ', '+', substr($content, $pos3 + 1)));
        $mime = $this->fileInfo->buffer($content);
        if (false == $mime) {
            $mime = substr($content, $pos1 + 1, $pos2 - $pos1 - 1);
        }
        $ext = null;
        if ($this->autoExt) {
            $ext = Mime::getExtensionsFromMime($mime);
            if (empty($ext[0])) {
                return $this->getRes(null, self::ERR_NO_MIME);
            }
            $ext = $ext[0];
        }
        $cbWrite = function ($file, $content) {
            if ($content['type'] === 'content') {
                return (bool)file_put_contents($file, $content['value'], LOCK_EX);
            } else {
                return copy($content['value'], $file);
            }
        };
        return $this->doUpload(array(
            'type' => 'content',
            'value' => $content
        ), $cbWrite, $ext);
    }

    /**
     * upload one file
     * enctype="multipart/form-data"
     *
     * @param string $name
     * @param array|null $files
     * @return array array('value'=>null,'code'=>0,'message'=>'');
     */
    function uploadPost($name, $files = null)
    {
        if (!isset($files)) {
            $files = $_FILES;
        }
        if (empty($files[$name]) || !is_string($files[$name]['name'])) {
            return $this->getRes(null, self::ERR_NO_FILE);
        }
        $file = $files[$name];
        return $this->doUploadPost($file);
    }

    /**
     *
     * @param $file
     * @return array array('value'=>null,'code'=>0,'message'=>'');
     */
    protected function doUploadPost($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->getRes(null, $file['error']);
        }
        $mime = $this->fileInfo->file($file['tmp_name']);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($this->autoExt) {
            $ext = Mime::getExtensionsFromMime($mime);
            if (!empty($ext[0])) {
                $ext = $ext[0];
            }
        }
        $cbWrite = function ($fileDst, $content) {
            if ($content['type'] === 'file') {
                if (is_uploaded_file($content['value'])) {
                    return move_uploaded_file($content['value'], $fileDst);
                } else {
                    return copy($content['value'], $fileDst);
                }
            } else {
                return (bool)file_put_contents($fileDst, $content['value']);
            }
        };
        return $this->doUpload(array(
            'type' => 'file',
            'name' => $file['name'],
            'value' => $file['tmp_name']
        ), $cbWrite, $ext);
    }

    /**
     * upload local file
     *
     * @param string $path
     * @param mixed $fileType
     *
     * @return array array('value'=>null,'code'=>0,'message'=>'');
     */
    function uploadLocal($path, $fileType = null)
    {
        if (!is_file($path)) {
            return $this->getRes(null, self::ERR_NO_FILE);
        }
        $file = array();
        $file['name'] = basename($path);
        $file['type'] = $fileType;
        $file['size'] = filesize($path);
        $file['tmp_name'] = $path;
        $file['error'] = UPLOAD_ERR_OK;
        return $this->doUploadPost($file);
    }

    /**
     * upload image from binary
     *
     * @param string $content
     *
     * @return array
     */
    function uploadBinary($content)
    {
        $type = $this->fileInfo->buffer($content);
        $content = 'data:' . $type . ';base64,' . chunk_split(base64_encode($content));
        return $this->uploadBase64($content);
    }

    /**
     *
     * @param array $idArr
     *
     * @return array
     */
    protected function getListById($idArr)
    {
        $idArr = array_filter($idArr);
        if (empty($idArr)) {
            return array();
        }
        $idArr = array_unique($idArr);
        $idPlaceholder = str_repeat('?, ', count($idArr) - 1) . '?';
        $pdo = $this->getPdo();
        $st = $pdo->prepare('select * from ' . $this->table . ' where id in(' . $idPlaceholder . ')');
        $st->execute($idArr);
        $list = $st->fetchAll(PDO::FETCH_OBJ);
        foreach ($list as $k => $v) {
            $v = $this->format($v);
            $list[$k] = $v;
        }
        return $list;
    }

    /**
     *
     * @param stdClass|array $row
     *
     * @return stdClass
     */
    function format($row)
    {
        $type = gettype($row);
        settype($row, 'object');
        $row->md5 = bin2hex($row->md5);
        $row->url = null;
        $row->file = null;
        if (isset($this->url, $row->path)) {
            $row->url = $this->url . '/' . $row->path;
        }
        if (isset($this->dir)) {
            $row->file = $this->dir . '/' . $row->path;
        }
        settype($row, $type);
        return $row;
    }

    /**
     *
     * @param array $content
     *            type
     *            value
     * @param callable $cbWrite
     *            return array
     * @param string $ext
     *
     * @return array
     */
    protected function doUpload($content, $cbWrite, $ext)
    {
        if (empty($this->dir)) {
            return $this->getRes(null, self::ERR_BASEDIR);
        }
        if (isset($this->extensions) && !in_array(strtolower($ext), $this->extensions)) {
            return $this->getRes(null, self::ERR_EXTENSION, ', ext=' . $ext);
        }
        if ($content['type'] === 'file' && !is_file($content['value'])) {
            return $this->getRes(null, self::ERR_NO_FILE, 'file not found');
        }
        $md5 = null;
        if ($content['type'] === 'file') {
            $md5 = md5_file($content['value'], true);
        } elseif ($content['type'] === 'content') {
            $md5 = md5($content['value'], true);
        } else {
            user_error('unknown type, type=' . $content['type'], E_USER_ERROR);
        }
        $funcMkdir = function ($dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        };
        $pdo = $this->getPdo();
        $st = $pdo->prepare('select * from ' . $this->table . ' where `md5`=?');
        $st->execute(array(
            $md5
        ));
        $row = $st->fetchObject();
        // records found in database
        if (false !== $row) {
            $fileUploaded = $this->dir . '/' . $row->path;
            // repair uploaded file
            if (!is_file($fileUploaded)) {
                $funcMkdir(dirname($fileUploaded));
                if (!$cbWrite($fileUploaded, $content)) {
                    return $this->getRes(null, self::ERR_WRITE);
                }
            }
            $row = $this->format($row);
            return $this->getRes($row, self::ERR_OK);
        }
        $path = bin2hex($md5);
        $subDir = substr($path, 0, 3) . '/' . substr($path, 3, 3);
        $funcMkdir($this->dir . '/' . $subDir);
        $fileName = $subDir . '/' . substr($path, 6);
        if (isset($ext)) {
            $fileName .= '.' . $ext;
        }
        $file = $this->dir . '/' . $fileName;
        $filesize = null;
        // filesize must be got before $cbWrite
        if ($content['type'] === 'file') {
            $filesize = filesize($content['value']);
        } elseif ($content['type'] === 'content') {
            $filesize = strlen($content['value']);
        }
        if (!$cbWrite($file, $content)) {
            return $this->getRes(null, self::ERR_WRITE);
        }
        $row = new stdClass();
        $row->md5 = $md5;
        $row->name = isset($content['name']) ? $content['name'] : null;
        $row->path = $fileName;
        $row->size = $filesize;
        if (!$pdo->prepare(
            'insert into ' . $this->table . '(`md5`,`name`,`path`,`size`) values(:md5,:name,:path,:size)')->execute(
            (array)$row)) {
            return $this->getRes(null, self::ERR_DB);
        }
        $row->id = (int)$pdo->lastInsertId();
        $row = $this->format($row);
        return $this->getRes($row, self::ERR_OK);
    }
}