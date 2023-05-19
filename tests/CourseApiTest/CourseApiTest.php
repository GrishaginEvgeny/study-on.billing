<?php

namespace App\Tests;

use App\Entity\Transaction;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseApiTest extends AbstractTest
{
    private array $usersCredentials = [
        ['username' => 'usualuser@study.com', 'password' => 'user'],
        ['username' => 'admin@study.com', 'password' => 'admin']
    ];

    protected function getFixtures(): array
    {
        $paymentService = $this->getContainer()->get(\App\Services\PaymentService::class);
        $userPassHasher = $this->getContainer()->get(UserPasswordHasherInterface::class);
        return [new \App\DataFixtures\UserFixtures($userPassHasher, $paymentService),
            new \App\DataFixtures\CourseFixtures()];
    }

    public function testGetCourses()
    {
        $client = $this->getClient();
        $client->request('GET', '/api/v1/courses', [], [], ['CONTENT_TYPE' => 'application/json']);
        $content = json_decode($client->getResponse()->getContent(), true);
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        $coursesFromRepository = [];
        foreach ($courses as $course) {
            $coursesFromRepository[] = [
                'course_code' => $course->getCharacterCode(),
                'type' => $course->getTypeCode(),
                'price' => $course->getCost()
            ];
        }
        $this->assertSame($content, $coursesFromRepository);
    }

    public function testGetCourse()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            $client->request(
                'GET',
                "/api/v1/courses/{$course->getCharacterCode()}",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json']
            );
            $content = json_decode($client->getResponse()->getContent(), true);
            $courseFromRepository = [
                'course_code' => $course->getCharacterCode(),
                'type' => $course->getTypeCode(),
                'price' => $course->getCost()
            ];
            $this->assertSame($content, $courseFromRepository);
        }
    }

    public function testGetCourseWithWrongCode()
    {
        $client = $this->getClient();
        $translator = $this->getContainer()->get('translator');
        $client->request(
            'GET',
            "/api/v1/courses/wrongcourse123",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseNotFound();
        $this->assertSame($translator->trans('errors.course.doesnt_exist', [], 'validators'), $content['message']);
    }

    public function testPayForCourseSuccessfully()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findLessThenByCost(500);
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                    $client->request(
                        'POST',
                        "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [],
                        [],
                        ['CONTENT_TYPE' => 'application/json',
                        'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                    );
                    $this->assertResponseCode(201);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(true, $courseContent["success"]);
                }
            }
        }
    }

    public function testPayForCourseWhichNotExist()
    {
        $client = $this->getClient();
        $translator = $this->getContainer()->get('translator');
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request(
                'POST',
                "/api/v1/courses/nonexisted/pay",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseCode(404);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(404, $courseContent["code"]);
            $this->assertEquals($translator->trans(
                'errors.course.doesnt_exist',
                [],
                'validators'
            ), $courseContent["message"]);
        }
    }

    public function testPayForCourseWithNonAuth()
    {
        $client = $this->getClient();
        $translator = $this->getContainer()->get('translator');
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                $client->request(
                    'POST',
                    "/api/v1/courses/{$course->getCharacterCode()}/pay",
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json']
                );
                $this->assertResponseCode(401);
                $content = json_decode($client->getResponse()->getContent(), true);
                $this->assertEquals(401, $content["code"]);
                $this->assertEquals($translator->trans(
                    'errors.user.doesnt_auth',
                    [],
                    'validators'
                ), $content["message"]);
            }
        }
    }

    public function testPayForFreeCourse()
    {
        $client = $this->getClient();
        $translator = $this->getContainer()->get('translator');
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "free") {
                    $client->request(
                        'POST',
                        "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [],
                        [],
                        ['CONTENT_TYPE' => 'application/json',
                        'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                    );
                    $this->assertResponseCode(406);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(406, $courseContent["code"]);
                    $this->assertArrayHasKey('errors', $courseContent);
                    $this->assertContains($translator->trans(
                        'errors.pay.buy_free_course',
                        [],
                        'validators'
                    ), $courseContent["errors"]);
                }
            }
        }
    }

    public function testPayForCourseWithNotEnoughMoney()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usualuser@study.com', 'password' => 'user'])
        );
        $translator = $this->getContainer()->get('translator');
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findGreaterThenByCost(1000);
        foreach ($courses as $course) {
            $client->request(
                'POST',
                "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseCode(406);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(406, $courseContent["code"]);
            $textForResponse = $course->getTypeCode() === 'rent'
                ? $translator->trans('errors.pay.not_enough_rent', [], 'validators')
                : $translator->trans('errors.pay.not_enough_buy', [], 'validators');
            $this->assertArrayHasKey('errors', $courseContent);
            $this->assertContains($textForResponse, $courseContent["errors"]);
        }
    }


    public function testPayForBoughtCourse()
    {
        $client = $this->getClient();
        $translator = $this->getContainer()->get('translator');
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findLessThenByCost(500);
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                    $client->request(
                        'POST',
                        "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [],
                        [],
                        ['CONTENT_TYPE' => 'application/json',
                        'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                    );
                    $this->assertResponseCode(201);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(true, $courseContent["success"]);

                    $client->request(
                        'POST',
                        "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [],
                        [],
                        ['CONTENT_TYPE' => 'application/json',
                        'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                    );

                    $this->assertResponseCode(406);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(406, $courseContent["code"]);
                    $textForResponse = $course->getTypeCode() === 'rent' ?
                        $translator->trans('errors.pay.already_rented', [], 'validators') :
                        $translator->trans('errors.pay.already_purchased', [], 'validators');
                    $this->assertArrayHasKey('errors', $courseContent);
                    $this->assertContains($textForResponse, $courseContent["errors"]);
                }
            }
        }
    }

    public function testTransactionsWithoutFilter()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => \App\Entity\Course::BUY_TYPE, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request(
                'POST',
                "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request(
                'GET',
                '/api/v1/transactions',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseOk();
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(2, count($transactionContent));
            foreach ($transactionContent as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
            }
        }
    }

    public function testTransactionsWithFilterOnType()
    {
        $client = $this->getClient();
        $transactionRepository = $this->getContainer()->get(TransactionRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        foreach ($this->usersCredentials as $user) {
            $userRepo = $userRepository->findOneBy(['email' => $user['username']]);
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request(
                'GET',
                '/api/v1/transactions?type=deposit',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseOk();
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $repositoryArrayCount = count($transactionRepository
                ->getTransactionsByFilters(Transaction::DEPOSIT_TYPE, null, null, $userRepo));
            $this->assertCount($repositoryArrayCount, $transactionContent);
            foreach ($transactionContent as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
                $this->assertArrayHasKey('type', $transaction);
                $this->assertSame('deposit', $transaction['type']);
            }
            $client->request(
                'GET',
                '/api/v1/transactions?type=payment',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
            );
            $this->assertResponseOk();
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $repositoryArrayCount = count($transactionRepository
                ->getTransactionsByFilters(Transaction::PAYMENT_TYPE, null, null, $userRepo));
            $this->assertCount($repositoryArrayCount, $transactionContent);
            foreach ($transactionContent as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
                $this->assertArrayHasKey('type', $transaction);
                $this->assertSame('payment', $transaction['type']);
            }
        }
    }

    public function testTransactionsWithFilterOnCourseCode()
    {
        $client = $this->getClient();
        $transactionRepository = $this->getContainer()->get(TransactionRepository::class);
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        foreach ($this->usersCredentials as $user) {
            $userRepo = $userRepository->findOneBy(['email' => $user['username']]);
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $courses = $courseRepository->findAll();
            foreach ($courses as $course) {
                $client->request(
                    'GET',
                    "/api/v1/transactions?course_code={$course->getCharacterCode()}",
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                );
                $this->assertResponseOk();
                $transactionContent = json_decode($client->getResponse()->getContent(), true);
                $repositoryArrayCount = count($transactionRepository
                    ->getTransactionsByFilters(null, $course->getCharacterCode(), null, $userRepo));
                $this->assertCount($repositoryArrayCount, $transactionContent);
                foreach ($transactionContent as $transaction) {
                    $this->assertArrayHasKey('created_at', $transaction);
                    $this->assertArrayHasKey('course_code', $transaction);
                    $this->assertSame($course->getCharacterCode(), $transaction['course_code']);
                }
            }
        }
    }

    public function testTransactionsWithFilterOnFlag()
    {
        $client = $this->getClient();
        $transactionRepository = $this->getContainer()->get(TransactionRepository::class);
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        foreach ($this->usersCredentials as $user) {
            $userRepo = $userRepository->findOneBy(['email' => $user['username']]);
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $courses = $courseRepository->findOneBy(['type' => \App\Entity\Course::RENT_TYPE]);
                $client->request(
                    'GET',
                    "/api/v1/transactions?skip_expired=true&type=payment",
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]
                );
                $this->assertResponseOk();
                $transactionContent = json_decode($client->getResponse()->getContent(), true);
                $repositoryArrayCount = count($transactionRepository
                    ->getTransactionsByFilters(Transaction::PAYMENT_TYPE, null, true, $userRepo));
                $this->assertCount($repositoryArrayCount, $transactionContent);
            foreach ($transactionContent as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
                $this->assertArrayHasKey('expired_at', $transaction);
                $this->assertNotNull($transaction['expired_at']);
                $courseTransaction = $transactionRepository
                    ->getTransactionsByFilters(Transaction::PAYMENT_TYPE, $transaction["course_code"], null, $userRepo);
                $this->assertGreaterThan(
                    (new \DateTimeImmutable($courseTransaction[0]->getExpiredAt())),
                    (new \DateTimeImmutable('now'))
                );
            }
        }
    }

    public function testAddCourseSuccessfully()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $userRepo = $userRepository->findOneBy(['email' => 'admin@study.com']);
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "rent", "title" => "test", "code" => "test", "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(201);
        $this->assertArrayHasKey("success", $courseContent);
    }

    public function testAddWithWrongCode()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $translator = static::getContainer()->get('translator');
        $userRepo = $userRepository->findOneBy(['email' => 'admin@study.com']);
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "rent", "title" => "test", "code" => "%%%%%%", "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertArrayHasKey("errors", $courseContent);
        $this->assertContains(
            $translator->trans('errors.course.slug.wrong_regex', [], 'validators'),
            $courseContent["errors"]
        );
    }

    public function testAddWithWrongLenght()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $userRepo = $userRepository->findOneBy(['email' => 'admin@study.com']);
        $translator = static::getContainer()->get('translator');
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "rent", "title" => str_repeat("a", 256),
            "code" => str_repeat(
                "a",
                256
            ),
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertArrayHasKey("errors", $courseContent);
        $this->assertContains(
            $translator->trans('errors.course.slug.too_big', [], 'validators'),
            $courseContent["errors"]
        );
        $this->assertContains(
            $translator->trans('errors.course.name.too_big', [], 'validators'),
            $courseContent["errors"]
        );
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "rent", "title" => "",
            "code" => "",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertArrayHasKey("errors", $courseContent);
        $this->assertArrayHasKey("errors", $courseContent);
        $this->assertContains(
            $translator->trans('errors.course.slug.too_tiny', [], 'validators'),
            $courseContent["errors"]
        );
        $this->assertContains(
            $translator->trans('errors.course.name.too_tiny', [], 'validators'),
            $courseContent["errors"]
        );
    }

    public function testAddWithTypeCostConflicts()
    {
        $client = $this->getClient();
        $translator = static::getContainer()->get('translator');
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $userRepo = $userRepository->findOneBy(['email' => 'admin@study.com']);
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "free", "title" => "test",
            "code" => "test",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertContains($translator->trans(
            'errors.course.price.free_with_cost',
            [],
            'validators'
        ), $courseContent["errors"]);
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "rent", "title" => "test",
            "code" => "test",
            "price" => 0])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertContains($translator->trans(
            'errors.course.price.buyable_without_cost',
            [],
            'validators'
        ), $courseContent["errors"]);
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "asdadas", "title" => "test",
            "code" => "test",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(406);
        $this->assertContains($translator->trans(
            'errors.course.type.wrong_type',
            [],
            'validators'
        ), $courseContent["errors"]);
    }

    public function testAddExistedCourse()
    {
        $client = $this->getClient();
        $translator = static::getContainer()->get('translator');
        $courseRepository = $this->getContainer()->get(CourseRepository::class);
        $userRepository = $this->getContainer()->get(UserRepository::class);
        $userRepo = $userRepository->findOneBy(['email' => 'admin@study.com']);
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $allCourses = $courseRepository->findAll();
        foreach ($allCourses as $course) {
            $client->request(
                'POST',
                "/api/v1/courses",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
                json_encode(["type" => "buy", "title" => "test",
                "code" => $course->getCharacterCode(),
                "price" => 100])
            );
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertResponseCode(406);
            $this->assertContains($translator->trans(
                'errors.course.slug.non_unique',
                [],
                'validators'
            ), $courseContent["errors"]);
        }
    }

    public function testAddWithUnuathOrUsualUser()
    {
        $client = $this->getClient();
        $translator = static::getContainer()->get('translator');
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(["type" => "buy", "title" => "test",
            "code" => "test",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(403);
        $this->assertSame($translator->trans(
            'errors.user.access_denied',
            [],
            'validators'
        ), $courseContent["message"]);
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usualuser@study.com', 'password' => 'user'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "free", "title" => "test",
            "code" => "test",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(403);
        $this->assertSame($translator->trans(
            'errors.user.access_denied',
            [],
            'validators'
        ), $courseContent["message"]);
    }

    public function testEditNotExisted()
    {
        $client = $this->getClient();
        $translator = static::getContainer()->get('translator');
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'admin@study.com', 'password' => 'admin'])
        );
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $client->request(
            'POST',
            "/api/v1/courses/test/edit",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token],
            json_encode(["type" => "free", "title" => "test",
            "code" => "test123",
            "price" => 100])
        );
        $courseContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(404);
        $this->assertSame($translator->trans(
            'errors.course.doesnt_exist',
            [],
            'validators'
        ), $courseContent["message"]);
    }
}
