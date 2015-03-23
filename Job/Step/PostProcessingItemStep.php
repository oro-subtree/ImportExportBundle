<?php

namespace Oro\Bundle\ImportExportBundle\Job\Step;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;

use Oro\Bundle\BatchBundle\Step\ItemStep;
use Oro\Bundle\ImportExportBundle\Job\JobExecutor;
use Oro\Bundle\ImportExportBundle\Writer\EntityWriter;

class PostProcessingItemStep extends ItemStep
{
    /**
     * @var array|null
     */
    protected $postProcessingJobs;

    /**
     * @var array|null
     */
    protected $contextSharedKeys;

    /**
     * @var JobExecutor
     */
    protected $jobExecutor;

    /**
     * @param string $jobName
     */
    public function setPostProcessingJobs($jobName)
    {
        $this->postProcessingJobs = $this->scalarToArray($jobName);
    }

    /**
     * @param string $contextSharedKeys
     */
    public function setContextSharedKeys($contextSharedKeys)
    {
        $this->contextSharedKeys = $this->scalarToArray($contextSharedKeys);
    }

    /**
     * @param JobExecutor $jobExecutor
     */
    public function setJobExecutor(JobExecutor $jobExecutor)
    {
        $this->jobExecutor = $jobExecutor;
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeStepElements(StepExecution $stepExecution)
    {
        $stepExecution->getExecutionContext()->put(EntityWriter::SKIP_CLEAR, true);
        parent::initializeStepElements($stepExecution);
    }

    /**
     * {@inheritdoc}
     */
    public function doExecute(StepExecution $stepExecution)
    {
        $this->initializeStepElements($stepExecution);

        $stepExecutor = new PostProcessingStepExecutor();
        $stepExecutor
            ->setStepExecution($stepExecution)
            ->setJobExecutor($this->jobExecutor)
            ->setReader($this->reader)
            ->setProcessor($this->processor)
            ->setWriter($this->writer);
        if (null !== $this->batchSize) {
            $stepExecutor->setBatchSize($this->batchSize);
        }

        if ($this->contextSharedKeys) {
            $stepExecutor->setContextSharedKeys($this->contextSharedKeys);
        }

        if ($this->postProcessingJobs) {
            $jobType = $stepExecution->getJobExecution()->getJobInstance()->getType();
            foreach ($this->postProcessingJobs as $jobName) {
                $stepExecutor->addPostProcessingJob($jobType, $jobName);
            }
        }

        $stepExecutor->execute($this);
        $this->flushStepElements();
    }

    /**
     * @param string $scalar
     * @return array
     */
    protected function scalarToArray($scalar)
    {
        $result = explode(',', $scalar);
        $result = array_map('trim', $result);

        return $result;
    }
}
