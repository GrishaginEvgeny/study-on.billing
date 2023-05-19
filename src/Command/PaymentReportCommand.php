<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Services\Twig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

class PaymentReportCommand extends Command
{
    protected static $defaultName = 'payment:report';
    protected static $defaultDescription = 'Отчёт по оплатам для администратора.';

    private $twig;
    private TransactionRepository $transactionRepository;
    private MailerInterface $mailer;
    private TranslatorInterface $translator;

    protected function configure(): void
    {
    }

    public function __construct(
        Twig $twig,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        MailerInterface $mailer,
        TranslatorInterface $translator
    ) {
        $this->twig = $twig;
        $this->transactionRepository = $transactionRepository;
        $this->mailer = $mailer;
        $this->translator = $translator;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = $this->transactionRepository->getReportAboutCourses();
        if (count($report) > 0) {
            $total = 0;
            foreach ($report as $transaction) {
                $total += $transaction['totalSum'];
            }

            $start = new \DateTime('-1 month');
            $end = new \DateTime('now');
            $report = $this->twig->render(
                'report.html.twig',
                [
                    'transactions' => $report,
                    'date' => [
                        'start' => $start,
                        'end' => $end,
                    ],
                    'total' => $total
                ]
            );
            $strStart = $start->format('d.m.Y');
            $strEnd = $end->format('d.m.Y');

            try {
                $email = (new Email())
                    ->to(new Address($_ENV['REPORT_MAIL']))
                    ->from(new Address($_ENV['ADMIN_MAIL']))
                    ->subject($this->translator->trans('command.report.template') . " {$strStart} -- {$strEnd}.")
                    ->html($report);

                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $io->error($e->getMessage());
                $io->error($this->translator->trans('errors.command.report', [], 'validators'));

                return Command::FAILURE;
            }
        }

        $io->success($this->translator->trans('command.report.success'));
        return Command::SUCCESS;
    }
}
