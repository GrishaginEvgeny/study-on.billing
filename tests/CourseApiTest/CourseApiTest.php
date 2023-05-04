<?php

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CourseApiTest extends \App\Tests\AbstractTest
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
                'character_code' => $course->getCharacterCode(),
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
            $client->request('GET', "/api/v1/courses/{$course->getCharacterCode()}",
                [], [], ['CONTENT_TYPE' => 'application/json']);
            $content = json_decode($client->getResponse()->getContent(), true);
            $courseFromRepository = [
                'character_code' => $course->getCharacterCode(),
                'type' => $course->getTypeCode(),
                'price' => $course->getCost()
            ];
            $this->assertSame($content, $courseFromRepository);
        }
    }

    public function testGetCourseWithWrongCode()
    {
        $client = $this->getClient();
        $client->request('GET', "/api/v1/courses/wrongcourse123",
            [], [], ['CONTENT_TYPE' => 'application/json']);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseNotFound();
        $this->assertSame('Курс с таким символьным кодом не найден.', $content['message']);
    }

    public function testPayForCourseSuccessfully()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findLessThenByCost(500);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                    $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [], [], ['CONTENT_TYPE' => 'application/json',
                            'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
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
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/nonexisted/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(404);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(404, $courseContent["code"]);
            $this->assertEquals("Курс с таким символьным кодом не найден.", $courseContent["message"]);
        }
    }

    public function testPayForCourseWithNonAuth()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        foreach ($courses as $course) {
            if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                    [], [], ['CONTENT_TYPE' => 'application/json']);
                $this->assertResponseCode(401);
                $content = json_decode($client->getResponse()->getContent(), true);
                $this->assertEquals(401, $content["code"]);
                $this->assertEquals("Вы не авторизованы.", $content["message"]);
            }
        }
    }

    public function testPayForFreeCourse()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findAll();
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "free") {
                    $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [], [], ['CONTENT_TYPE' => 'application/json',
                            'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
                    $this->assertResponseCode(406);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(406, $courseContent["code"]);
                    $this->assertEquals("Этот курс бесплатен и не требует покупки.", $courseContent["message"]);
                }
            }
        }
    }

    public function testPayForCourseWithNotEnoughMoney()
    {
        $client = $this->getClient();
        $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usualuser@study.com', 'password' => 'user']));
        $this->assertResponseCode(200);
        $userContent = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $userContent);
        $token = $userContent['token'];
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findGreaterThenByCost(1000);
        foreach ($courses as $course) {
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(406);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(406, $courseContent["code"]);
            $textForResponse = $course->getTypeCode() === 'rent' ? 'аренды' : 'покупки';
            $this->assertEquals("У вас не достаточно средств на счёте для "
                . "{$textForResponse} этого курса.", $courseContent["message"]);
        }
    }


    public function testPayForBoughtCourse()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $courses = $courseRepository->findLessThenByCost(500);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            foreach ($courses as $course) {
                if ($course->getTypeCode() === "rent" || $course->getTypeCode() === "buy") {
                    $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [], [], ['CONTENT_TYPE' => 'application/json',
                            'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
                    $this->assertResponseCode(201);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(true, $courseContent["success"]);

                    $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                        [], [], ['CONTENT_TYPE' => 'application/json',
                            'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

                    $this->assertResponseCode(406);
                    $courseContent = json_decode($client->getResponse()->getContent(), true);
                    $this->assertEquals(406, $courseContent["code"]);
                    $textForResponse = $course->getTypeCode() === 'rent' ?
                        'Этот курс уже арендован и длительность аренды ещё не истекла.' :
                        'Этот курс уже куплен.';
                    $this->assertEquals($textForResponse, $courseContent["message"]);
                }
            }
        }
    }

    public function testTransactionsWithoutFilter()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', '/api/v1/transactions', [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
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
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', '/api/v1/transactions?type=deposit', [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseOk();
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertCount(1, $transactionContent);
            foreach ($transactionContent as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
                $this->assertArrayHasKey('type', $transaction);
                $this->assertSame('deposit', $transaction['type']);
            }

            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', '/api/v1/transactions?type=payment', [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseOk();
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertCount(1, $transactionContent);
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
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', "/api/v1/transactions?course_code={$course->getCharacterCode()}", [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseOk();
            $transactionUser = json_decode($client->getResponse()->getContent(), true);
            $this->assertCount(1, $transactionUser);
            foreach ($transactionUser as $transaction) {
                $this->assertArrayHasKey('created_at', $transaction);
                $this->assertSame($course->getCharacterCode(), $transaction['course_code']);
            }
        }
    }

    public function testTransactionsWithWrongFilterOnType()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', "/api/v1/transactions?type=wrongtype", [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(400);
            $transactionUser = json_decode($client->getResponse()->getContent(), true);
            $this->assertSame('Указанный тип не равен "deposit" или "payment".', $transactionUser["message"]);
        }
    }

    public function testTransactionsWithWrongFilterOnCode()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', "/api/v1/transactions?course_code=wrongtype", [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(404);
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertSame('Курс с таким символьным кодом не найден.', $transactionContent["message"]);
        }
    }

    public function testTransactionsWithWrongFlagOfExpires()
    {
        $client = $this->getClient();
        $courseRepository = $this->getContainer()->get(\App\Repository\CourseRepository::class);
        $course = $courseRepository->findOneBy(['type' => 3, 'cost' => 199.99]);
        foreach ($this->usersCredentials as $user) {
            $client->request('POST', '/api/v1/auth', [], [], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']]));
            $this->assertResponseCode(200);
            $userContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertArrayHasKey('token', $userContent);
            $token = $userContent['token'];
            $client->request('POST', "/api/v1/courses/{$course->getCharacterCode()}/pay",
                [], [], ['CONTENT_TYPE' => 'application/json',
                    'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(201);
            $courseContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertEquals(true, $courseContent["success"]);
            $client->request('GET', "/api/v1/transactions?skip_expired=wrongflag", [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            $this->assertResponseCode(400);
            $transactionContent = json_decode($client->getResponse()->getContent(), true);
            $this->assertSame('Флаг не равен "true" или "false".', $transactionContent["message"]);
        }
    }


}