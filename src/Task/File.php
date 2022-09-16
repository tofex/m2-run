<?php

namespace Tofex\Task\Task;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;
use Tofex\Core\Helper\Registry;
use Tofex\Help\Files;
use Tofex\Help\Variables;
use Tofex\Task\Helper\Data;
use Tofex\Task\Model\RunFactory;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2022 Tofex UG (http://www.tofex.de)
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class File
    extends Base
{
    /** @var Variables */
    protected $variableHelper;

    /** @var \Tofex\Core\Helper\Files */
    protected $coreFilesHelper;

    /** @var array */
    private $importFiles;

    /** @var string[] */
    private $importedFiles = [];

    /**
     * @param Files                                      $fileHelper
     * @param Registry                                   $registryHelper
     * @param Data                                       $helper
     * @param LoggerInterface                            $logger
     * @param Emulation                                  $appEmulation
     * @param DirectoryList                              $directoryList
     * @param TransportBuilder                           $transportBuilder
     * @param RunFactory                                 $runFactory
     * @param \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory
     * @param Variables                                  $variableHelper
     * @param \Tofex\Core\Helper\Files                   $coreFilesHelper
     */
    public function __construct(
        Files $fileHelper,
        Registry $registryHelper,
        Data $helper,
        LoggerInterface $logger,
        Emulation $appEmulation,
        DirectoryList $directoryList,
        TransportBuilder $transportBuilder,
        RunFactory $runFactory,
        \Tofex\Task\Model\ResourceModel\RunFactory $runResourceFactory,
        Variables $variableHelper,
        \Tofex\Core\Helper\Files $coreFilesHelper)
    {
        parent::__construct($fileHelper, $registryHelper, $helper, $logger, $appEmulation, $directoryList,
            $transportBuilder, $runFactory, $runResourceFactory);

        $this->variableHelper = $variableHelper;
        $this->coreFilesHelper = $coreFilesHelper;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function prepare()
    {
        if ($this->variableHelper->isEmpty($this->getImportPath())) {
            throw new Exception('No path to import specified');
        }

        if ($this->variableHelper->isEmpty($this->getArchivePath())) {
            throw new Exception('No archive path specified');
        }

        if ($this->variableHelper->isEmpty($this->getErrorPath())) {
            throw new Exception('No error path specified');
        }
    }

    /**
     * Load import files and executes for all these files the import method.
     *
     * @return void
     * @throws Exception
     */
    protected function runTask()
    {
        $suppressEmptyMails = $this->isSuppressEmptyMails();

        $importFiles = $this->determineImportFiles();

        $fileCounter = count($importFiles);

        if ($fileCounter) {
            for ($i = 0; $i < $fileCounter; $i++) {
                if (array_key_exists($i, $importFiles)) {
                    $this->logging->info(sprintf('Importing file %d/%d: %s', $i + 1, $fileCounter, $importFiles[ $i ]));

                    try {
                        $result = $this->importFile($importFiles[ $i ]);
                        $this->logging->debug(sprintf('Successfully finished import of file: %s', $importFiles[ $i ]));
                        $this->importedFiles[ $importFiles[ $i ] ] = $result;
                    } catch (Exception $exception) {
                        $this->logging->debug(sprintf('Could not finish import of file: %s because; %s',
                            $importFiles[ $i ], $exception->getMessage()));
                        $this->logging->error($exception);
                        $this->importedFiles[ $importFiles[ $i ] ] = false;
                    }
                }
            }
        } else {
            $this->setProhibitSummarySending(self::SUMMARY_TYPE_ALL, $suppressEmptyMails);

            $this->logging->info('Nothing to import');
        }
    }

    /**
     * @param bool $success
     *
     * @return void
     * @throws Exception
     */
    protected function dismantle(bool $success)
    {
        $archivePath = $this->getArchivePath();
        $errorPath = $this->getErrorPath();

        foreach ($this->importedFiles as $importedFile => $result) {
            $importedFileArchivePath = $this->coreFilesHelper->determineFilePath($result ? $archivePath : $errorPath);

            $importedFileArchiveFileName =
                $this->fileHelper->determineFilePath($this->getArchiveFileName(basename($importedFile)),
                    $importedFileArchivePath, true);

            if ( ! file_exists($importedFileArchivePath)) {
                if (mkdir($importedFileArchivePath, 0777, true)) {
                    $this->logging->info(sprintf('Archive path %s successful created', $importedFileArchivePath));
                } else {
                    $this->logging->error(sprintf('Cannot create archive path %s', $importedFileArchivePath));
                }
            }

            $this->logging->info(sprintf('Moving import file: %s to archive file: %s', $importedFile,
                $importedFileArchiveFileName));

            if ( ! $this->isTest()) {
                if ( ! rename($importedFile, $importedFileArchiveFileName)) {
                    throw new Exception(sprintf('Could not move import file: %s to archive file: %s', $importedFile,
                        $importedFileArchiveFileName));
                }
            } else {
                if ( ! copy($importedFile, $importedFileArchiveFileName)) {
                    throw new Exception(sprintf('Could not move import file: %s to archive file: %s', $importedFile,
                        $importedFileArchiveFileName));
                }
            }
        }
    }

    /**
     * @param string $importFileName
     *
     * @return string
     */
    abstract protected function getArchiveFileName(string $importFileName): string;

    /**
     * Determine the files to import and returns them.
     *
     * @return string[]             a list of import files
     * @throws Exception
     */
    protected function determineImportFiles(): array
    {
        if ($this->importFiles === null) {
            $path = $this->getImportPath();

            $this->importFiles = $this->coreFilesHelper->determineFilesFromFilePath($path);

            $filePattern = $this->getFilePattern();

            if ( ! $this->variableHelper->isEmpty($filePattern)) {
                $filteredImportFiles = [];

                $filePattern = preg_replace('/\//', '\\\/', $filePattern);

                foreach ($this->importFiles as $importFile) {
                    if (preg_match(sprintf('/%s/', $filePattern), $importFile)) {
                        $filteredImportFiles[] = $importFile;
                    }
                }

                $this->importFiles = $filteredImportFiles;
            }
        }

        return $this->importFiles;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function hasImportFiles(): bool
    {
        $importFiles = $this->determineImportFiles();

        return count($importFiles) > 0;
    }

    /**
     * Executes the task for the given file.
     *
     * @param string $importFile the path to the import file
     *
     * @return bool
     *
     * @throws Exception
     */
    abstract protected function importFile(string $importFile): bool;

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getImportPath(): string
    {
        return $this->getTaskSetting('path');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getFilePattern(): string
    {
        return $this->getTaskSetting('file_pattern');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getArchivePath(): string
    {
        return $this->getTaskSetting('archive_path');
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getErrorPath(): string
    {
        return $this->getTaskSetting('error_path');
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isSuppressEmptyMails(): bool
    {
        return $this->getTaskSetting('suppress_empty_mails', false, true);
    }
}
