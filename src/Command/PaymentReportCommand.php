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

class PaymentReportCommand extends Command
{
    protected static $defaultName = 'payment:report';
    protected static $defaultDescription = 'Отчёт по оплатам для администратора.';

    private $twig;
    private TransactionRepository $transactionRepository;
    private MailerInterface $mailer;

    protected function configure(): void
    {
    }

    public function __construct(
        Twig $twig,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        MailerInterface $mailer
    ) {
        $this->twig = $twig;
        $this->transactionRepository = $transactionRepository;
        $this->mailer = $mailer;
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
                    ->subject("Отчет об оплаченных курсах в период {$strStart} -- {$strEnd}.")
                    ->html($report);

                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $io->error($e->getMessage());
                $io->error('Ошибка при формировании и отправке отчета.');

                return Command::FAILURE;
            }
        }

        $io->success('Отчёт успешно отправлен');
        return Command::SUCCESS;
    }
}
