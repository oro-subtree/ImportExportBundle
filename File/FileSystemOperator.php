<?php

namespace Oro\Bundle\ImportExportBundle\File;

class FileSystemOperator
{
    /**
     * @var string
     */
    protected $cacheDirectory;

    /**
     * @var
     */
    protected $temporaryDirectory;

    /**
     * @param $cacheDirectory
     */
    public function __construct($cacheDirectory)
    {
        $this->cacheDirectory = $cacheDirectory;
    }

    /**
     * @return string
     * @throws \LogicException
     */
    public function getTemporaryDirectory()
    {
        if (!$this->temporaryDirectory) {
            $cacheDirectory = rtrim($this->cacheDirectory, DIRECTORY_SEPARATOR);
            $temporaryDirectory = $cacheDirectory . DIRECTORY_SEPARATOR . 'import_export';
            if (!file_exists($temporaryDirectory) && !is_dir($temporaryDirectory)) {
                mkdir($temporaryDirectory);
            }

            if (!is_readable($temporaryDirectory)) {
                throw new \LogicException('Import/export directory is not readable');
            }
            if (!is_writable($temporaryDirectory)) {
                throw new \LogicException('Import/export directory is not writeable');
            }

            $this->temporaryDirectory = $temporaryDirectory;
        }

        return $this->temporaryDirectory;
    }

    /**
     * @param $fileName
     * @return \SplFileObject
     * @throws \LogicException
     */
    public function getTemporaryFile($fileName)
    {
        $temporaryDirectory = $this->getTemporaryDirectory();
        $fullFileName = $temporaryDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($fullFileName) || !is_file($fullFileName) || !is_readable($fullFileName)) {
            throw new \LogicException(sprintf('Can\'t read file %s', $fileName));
        }

        return new \SplFileObject($fullFileName);
    }

    /**
     * @param string $filePrefix
     * @param string $fileExtension
     * @return string
     */
    public function generateTemporaryFileName($filePrefix, $fileExtension = 'tmp')
    {
        $temporaryDirectory = $this->getTemporaryDirectory();

        do {
            $fileName = sprintf(
                '%s%s%s.%s',
                $temporaryDirectory,
                DIRECTORY_SEPARATOR,
                preg_replace('~\W~', '_', uniqid($filePrefix . '_', true)),
                $fileExtension
            );
        } while (file_exists($fileName));

        return $fileName;
    }
}