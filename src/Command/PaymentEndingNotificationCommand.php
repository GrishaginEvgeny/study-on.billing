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

class PaymentEndingNotificationCommand extends Command
{
    protected static $defaultName = 'payment:ending:notification';
    protected static $defaultDescription = 'Комманд для рассылки на почту истекающий аренд.';

    private $twig;
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;
    private MailerInterface $mailer;
    private TranslatorInterface $translator;

    public function __construct(
        Twig $twig,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        MailerInterface $mailer,
        TranslatorInterface $translator
    ) {
        $this->twig = $twig;
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->translator = $translator;
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $allUsers = $this->userRepository->findAll();
        foreach ($allUsers as $user) {
            $rentWhichExpiresTommorow = $this->transactionRepository->getRentTransactionsExpiresInOneDayOnUser($user);
            if (count($rentWhichExpiresTommorow) > 0) {
                $report = $this->twig->render(
                    'expire-soon.html.twig',
                    [
                        'transactions' => $rentWhichExpiresTommorow,
                    ]
                );

                try {
                    $email = (new Email())
                        ->to(new Address($user->getEmail()))
                        ->from(new Address($_ENV['ADMIN_MAIL']))
                        ->subject($this->translator->trans('command.expired.template'))
                        ->html($report);

                    $this->mailer->send($email);
                } catch (TransportExceptionInterface $e) {
                    $io->error($e->getMessage());
                    $io->error(
                        $this->translator->trans('errors.command.expired', [], 'validators')
                        . ' ' . $user->getEmail() . '.'
                    );

                    return Command::FAILURE;
                }
            }
        }
        $io->success($this->translator->trans('command.expired.success'));
        return Command::SUCCESS;
    }
}
