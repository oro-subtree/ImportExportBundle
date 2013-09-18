<?php

namespace Oro\Bundle\ImportExportBundle\Tests\Unit\Reader;

use Oro\Bundle\ImportExportBundle\Processor\RegistryDelegateProcessor;

class RegistryDelegateProcessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var RegistryDelegateProcessor
     */
    protected $processor;

    protected function setUp()
    {
        $this->registry = $this->getMock('Oro\Bundle\ImportExportBundle\Processor\ProcessorRegistry');
        $this->processor = new RegistryDelegateProcessor($this->registry);
    }

    public function testSetStepExecution()
    {
        $stepExecution = $this->getMockBuilder('Oro\Bundle\BatchBundle\Entity\StepExecution')
            ->disableOriginalConstructor()->getMock();
        $this->processor->setStepExecution($stepExecution);

        $this->assertAttributeEquals($stepExecution, 'stepExecution', $this->processor);
    }

    public function testProcessStepExecutionAwareProcessor()
    {
        $entityName = 'entity_name';
        $processorAlias = 'processor_alias';
        $stepExecution = $this->getMockStepExecution(
            array('entityName' => $entityName, 'processorAlias' => $processorAlias)
        );
        $item = $this->getMock('MockItem');

        $delegateProcessor = $this->getMock('Oro\Bundle\ImportExportBundle\Processor\StepExecutionAwareProcessor');

        $this->registry->expects($this->once())->method('getProcessor')
            ->with($entityName, $processorAlias)
            ->will($this->returnValue($delegateProcessor));

        $delegateProcessor->expects($this->once())->method('setStepExecution')->with($stepExecution);
        $delegateProcessor->expects($this->once())->method('process')->with($item);

        $this->processor->setStepExecution($stepExecution);
        $this->processor->process($item);
    }

    public function testProcessSimpleProcessor()
    {
        $entityName = 'entity_name';
        $processorAlias = 'processor_alias';
        $stepExecution = $this->getMockStepExecution(
            array('entityName' => $entityName, 'processorAlias' => $processorAlias)
        );
        $item = $this->getMock('MockItem');

        $delegateProcessor = $this->getMock('Oro\Bundle\ImportExportBundle\Processor\ProcessorInterface');

        $this->registry->expects($this->once())->method('getProcessor')
            ->with($entityName, $processorAlias)
            ->will($this->returnValue($delegateProcessor));

        $delegateProcessor->expects($this->never())->method('setStepExecution');
        $delegateProcessor->expects($this->once())->method('process')->with($item);

        $this->processor->setStepExecution($stepExecution);
        $this->processor->process($item);
    }


    /**
     * @expectedException \Oro\Bundle\ImportExportBundle\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Configuration of processor must contain "entityName" and "processorAlias" options.
     */
    public function testProcessFailsWhenNoConfigurationProvided()
    {
        $this->processor->setStepExecution($this->getMockStepExecution(array()));
        $this->processor->process($this->getMock('MockItem'));
    }

    /**
     * @expectedException \Oro\Bundle\ImportExportBundle\Exception\LogicException
     * @expectedExceptionMessage Step execution entity must be injected to processor.
     */
    public function testProcessFailsWhenNoStepExecution()
    {
        $this->processor->process($this->getMock('MockItem'));
    }

    /**
     * @param array $jobInstanceRawConfiguration
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getMockStepExecution(array $jobInstanceRawConfiguration)
    {
        $jobInstance = $this->getMockBuilder('Oro\Bundle\BatchBundle\Entity\JobInstance')
            ->disableOriginalConstructor()
            ->getMock();

        $jobInstance->expects($this->once())->method('getRawConfiguration')
            ->will($this->returnValue($jobInstanceRawConfiguration));

        $jobExecution = $this->getMockBuilder('Oro\Bundle\BatchBundle\Entity\JobExecution')
            ->disableOriginalConstructor()
            ->getMock();

        $jobExecution->expects($this->once())->method('getJobInstance')
            ->will($this->returnValue($jobInstance));

        $stepExecution = $this->getMockBuilder('Oro\Bundle\BatchBundle\Entity\StepExecution')
            ->disableOriginalConstructor()
            ->getMock();
        $stepExecution->expects($this->once())->method('getJobExecution')
            ->will($this->returnValue($jobExecution));

        return $stepExecution;
    }
}
