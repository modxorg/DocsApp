<?php

namespace MODXDocs\CLI\Commands;

use MODXDocs\Services\FrontMatterValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'validate:frontmatter',
    description: 'Parse markdown YAML frontmatter and fail on duplicate keys, invalid YAML, or translation paths that do not match an English file'
)]
class ValidateFrontMatter extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'paths',
            InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
            'Paths inside the docs directory to scan, for example 3.x or 3.x/en. Omit to scan all documentation.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paths = $input->getArgument('paths');
        if (!is_array($paths)) {
            $paths = [];
        }

        $result = (new FrontMatterValidator())->validate($paths);
        if ($result['errors'] === []) {
            $output->writeln(sprintf('<info>Frontmatter OK in %d markdown files.</info>', $result['files']));
            return Command::SUCCESS;
        }

        foreach ($result['errors'] as $error) {
            $output->writeln(sprintf('<error>%s: %s</error>', $error['file'], $error['message']));
        }

        $output->writeln(sprintf(
            '<error>Found %d frontmatter issue(s) in %d markdown files.</error>',
            count($result['errors']),
            $result['files']
        ));

        return Command::FAILURE;
    }
}
