<?php

namespace FS\SolrBundle\Console;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper class which renders a table of errored entities
 */
class ConsoleErrorListOutput
{
    /**
     * @var OutputInterface
     */
    private $output = null;

    /**
     * @var array
     */
    private $errors = array();

    /**
     * @var TableHelper
     */
    private $tableHelperSet = null;

    /**
     * @param OutputInterface $output
     * @param TableHelper     $tableHelperSet
     * @param array           $errors
     */
    public function __construct(OutputInterface $output, TableHelper $tableHelperSet, array $errors)
    {
        $this->output = $output;
        $this->errors = $errors;
        $this->tableHelperSet = $tableHelperSet;
    }

    /**
     * Renders a table of all errored entities
     */
    public function render()
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Errors:</info>');
        $rows = array();
        foreach ($this->errors as $error) {
            $rows[] = array($error->getEntity(), $error->getResultId(), $error->getMessage());
        }

        $this->tableHelperSet->setHeaders(array('Entity', 'ID', 'Error'))
            ->setRows($rows);

        $this->tableHelperSet->render($this->output);
    }
} 