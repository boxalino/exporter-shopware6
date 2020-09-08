<?php
namespace Boxalino\Exporter\Service\Util;

class FileHandler
{

    /**
     * @var string
     */
    public $XML_DELIMITER = ',';

    /**
     * @var string
     */
    public $XML_ENCLOSURE = '"';

    /**
     * @var string
     */
    protected $_mainDir;

    /**
     * @var string
     */
    protected $account;
    protected $_dir;
    protected $type;
    protected $_files = [];

    /**
     * Prepares rootr directory where the exported files are to be stored
     */
    public function init() : void
    {
        $this->_dir = $this->_mainDir . $this->account . '_' . $this->type . '_' . microtime(true);
        if (!file_exists($this->_dir)) {
            mkdir($this->_dir, 0777, true);
        }else{
            $this->delTree($this->_dir);
            mkdir($this->_dir, 0777, true);
        }
    }

    /**
     * @param string $dir
     * @return bool
     */
    protected function delTree(string $dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                self::delTree("$dir/$file");
            } else if (file_exists("$dir/$file")) {
                @unlink("$dir/$file");
            }
        }

        return rmdir($dir);
    }

    /**
     * @param string $file
     * @param array $data
     */
    public function savePartToCsv(string $file, array &$data) : void
    {
        $path = $this->getPath($file);
        $fh = fopen($path, 'a');
        foreach($data as $dataRow){
            fputcsv($fh, $dataRow, $this->XML_DELIMITER, $this->XML_ENCLOSURE);
        }

        fclose($fh);
        $data = null;
    }

    /**
     * @param string $file
     * @return string
     */
    public function getFileContents(string $file) : string
    {
        return file_get_contents($this->getPath($file));
    }

    /**
     * @param string $file
     * @return string
     */
    public function getPath(string $file) : string
    {
        //save
        if (!in_array($file, $this->_files)) {
            $this->_files[] = $file;
        }

        return $this->_dir . '/' . $file;
    }

    /**
     * @param $account
     * @return FileHandler
     */
    public function setAccount(string $account) : self
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @param string $type
     * @return FileHandler
     */
    public function setType(string $type) : self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param string $dirPath
     * @return FileHandler
     */
    public function setMainDir(string $dirPath) : self
    {
        $this->_mainDir = $dirPath;
        return $this;
    }

}
