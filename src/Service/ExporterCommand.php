<?php declare(strict_types=1);
namespace Boxalino\Exporter\Service;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExporterCommand
 *
 * Use to trigger the exporter
 * ex: php bin/console boxalino:exporter:run full|delta [account1]
 *
 * @package Boxalino\Exporter\Service
 */
class ExporterCommand extends Command
{
    protected static $defaultName = 'boxalino:exporter:run';

    protected $exporterFull;
    protected $exporterDelta;

    public function __construct(
        ExporterDeltaInterface $deltaExporter,
        ExporterFullInterface $fullExporter
    ){
        parent::__construct();

        $this->exporterDelta = $deltaExporter;
        $this->exporterFull = $fullExporter;
    }

    protected function configure()
    {
        $this->setDescription("Boxalino Full Data export command. Accepts parameters [delta|full] [account]")
            ->setHelp("This command allows you to update the Boxalino SOLR data index with your current data.");

        $this->addArgument(
            "type", InputArgument::REQUIRED, "Exporter Type: full or delta"
        );

        $this->addArgument(
            "account", InputArgument::OPTIONAL, "Boxalino Account name"
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument("type");
        $account = $input->getArgument("account");
        $output->writeln('Boxalino ' . $type . ' data export start...');

        if($type == $this->exporterFull->getType())
        {
            if(!empty($account))
            {
                $this->exporterFull->setAccount($account);
            }
            try{
                $this->exporterFull->export();
            } catch (\Exception $exc)
            {
                $output->writeln($exc->getMessage());
            }
        }

        if($type == $this->exporterDelta->getType())
        {
            if(!empty($account))
            {
                $this->exporterDelta->setAccount($account);
            }

            try{
                $this->exporterDelta->export();
            } catch (\Exception $exc)
            {
                $output->writeln($exc->getMessage());
            }
        }

        $output->writeln("Boxalino export finished.");
        return 0;
    }

}
