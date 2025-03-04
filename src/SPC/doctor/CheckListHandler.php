<?php

declare(strict_types=1);

namespace SPC\doctor;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CheckListHandler
{
    /** @var AsCheckItem[] */
    private array $check_list = [];

    private array $fix_map = [];

    /**
     * @throws \ReflectionException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function __construct(private InputInterface $input, private OutputInterface $output)
    {
        $this->loadCheckList();
    }

    /**
     * @throws RuntimeException
     */
    public function runCheck(int $fix_policy = FIX_POLICY_DIE): void
    {
        foreach ($this->check_list as $item) {
            if ($item->limit_os !== null && $item->limit_os !== PHP_OS_FAMILY) {
                continue;
            }
            $this->output->write('Checking <comment>' . $item->item_name . '</comment> ... ');
            $result = call_user_func($item->callback);
            if ($result === null) {
                $this->output->writeln('skipped');
            } elseif ($result instanceof CheckResult) {
                if ($result->isOK()) {
                    $this->output->writeln('ok');
                    continue;
                }
                // Failed
                $this->output->writeln('<error>' . $result->getMessage() . '</error>');
                switch ($fix_policy) {
                    case FIX_POLICY_DIE:
                        throw new RuntimeException('Some check items can not be fixed !');
                    case FIX_POLICY_PROMPT:
                        if ($result->getFixItem() !== '') {
                            $helper = new QuestionHelper();
                            $question = new ConfirmationQuestion('Do you want to fix it? [Y/n] ', true);
                            if ($helper->ask($this->input, $this->output, $question)) {
                                $this->emitFix($result);
                            } else {
                                throw new RuntimeException('You cancelled fix');
                            }
                        } else {
                            throw new RuntimeException('Some check items can not be fixed !');
                        }
                        break;
                    case FIX_POLICY_AUTOFIX:
                        if ($result->getFixItem() !== '') {
                            $this->output->writeln('Automatically fixing ' . $result->getFixItem() . ' ...');
                            $this->emitFix($result);
                        } else {
                            throw new RuntimeException('Some check items can not be fixed !');
                        }
                        break;
                }
            }
        }
    }

    /**
     * Load Doctor check item list
     *
     * @throws \ReflectionException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    private function loadCheckList(): void
    {
        foreach (FileSystem::getClassesPsr4(__DIR__ . '/item', 'SPC\\doctor\\item') as $class) {
            $ref = new \ReflectionClass($class);
            foreach ($ref->getMethods() as $method) {
                $attr = $method->getAttributes(AsCheckItem::class);
                if (isset($attr[0])) {
                    /** @var AsCheckItem $instance */
                    $instance = $attr[0]->newInstance();
                    $instance->callback = [new $class(), $method->getName()];
                    $this->check_list[] = $instance;
                    continue;
                }
                $attr = $method->getAttributes(AsFixItem::class);
                if (isset($attr[0])) {
                    /** @var AsFixItem $instance */
                    $instance = $attr[0]->newInstance();
                    // Redundant fix item
                    if (isset($this->fix_map[$instance->name])) {
                        throw new RuntimeException('Redundant doctor fix item: ' . $instance->name);
                    }
                    $this->fix_map[$instance->name] = [new $class(), $method->getName()];
                }
            }
        }
        // sort check list by level
        usort($this->check_list, fn ($a, $b) => $a->level > $b->level ? -1 : ($a->level == $b->level ? 0 : 1));
    }

    private function emitFix(CheckResult $result)
    {
        $fix = $this->fix_map[$result->getFixItem()];
        $fix_result = call_user_func($fix, ...$result->getFixParams());
        if ($fix_result) {
            $this->output->writeln('<info>Fix done</info>');
        } else {
            $this->output->writeln('<error>Fix failed</error>');
            throw new RuntimeException('Some check item are not fixed');
        }
    }
}
