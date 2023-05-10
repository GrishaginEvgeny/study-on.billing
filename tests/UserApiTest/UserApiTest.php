<?php

use App\Repository\UserRepository;
use App\Tests\AbstractTest;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserApiTest extends AbstractTest
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

    public function testInvalidCredentialsAuth(): void
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/auth',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => '123', 'password' => '123'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame("Invalid credentials.", $content['message']);
        $this->assertResponseCode(401);
    }

    public function testValidCredentialsAuth(): void
    {
        $client = $this->getClient();
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $content = json_decode($client->getResponse()->getContent(), true);
            $this->assertResponseCode(200);
            $this->assertArrayHasKey('token', $content);
        }
    }

    public function testRegisterInvalidEmail(): void
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => '123', 'password' => 'ASDVsas123.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $content['errors'] = (array)$content['errors'];
        $this->assertNotCount(0, $content['errors']);
        $this->assertArrayHasKey('username', $content['errors']);
        $this->assertSame("Поле e-mail содержит некорректные данные.", $content['errors']['username']);
    }

    public function testRegisterBlankEmail(): void
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => '', 'password' => 'ASDVsas123.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $content['errors'] = (array)$content['errors'];
        $this->assertNotCount(0, $content['errors']);
        $this->assertArrayHasKey('username', $content['errors']);
        $this->assertSame("Поле e-mail не может быт пустым.", $content['errors']['username']);
    }

    public function testRegisterInvalidPassword(): void
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'test@test.test', 'password' => 'dasdadadsa'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $content['errors'] = (array)$content['errors'];
        $this->assertNotCount(0, $content['errors']);
        $this->assertArrayHasKey('password', $content['errors']);
        $this->assertSame(
            "Пароль должен содержать как один из спец. символов (.!@#$%^&*), " .
            "прописную и строчные буквы латинского алфавита и цифру.",
            $content['errors']['password']
        );
    }

    public function testRegisterWithPasswordWhichHaveLengthLessThenConstraint(): void
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'test@test.test', 'password' => 'Aa1.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $content['errors'] = (array)$content['errors'];
        $this->assertNotCount(0, $content['errors']);
        $this->assertArrayHasKey('password', $content['errors']);
        $this->assertSame(
            "Пароль должен содержать минимум 6 символов.",
            $content['errors']['password']
        );
    }


    public function testSuccessfullyRegister()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usernotindb@test.test', 'password' => 'Aa111Bb.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(201);
        $this->assertArrayHasKey('token', $content);
        $this->assertArrayHasKey('refresh_token', $content);
    }

    public function testGetUserWithUnAuth()
    {
        $client = $this->getClient();
        $client->request('GET', '/api/v1/users/current');
        $this->assertResponseCode(401);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $content);
        $this->assertSame("JWT Token not found", $content['message']);
    }

    public function testGetUserWithAuth()
    {
        $client = $this->getClient();
        foreach ($this->usersCredentials as $user) {
            $client->request(
                'POST',
                '/api/v1/auth',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username' => $user['username'], 'password' => $user['password']])
            );
            $content = json_decode($client->getResponse()->getContent(), true);
            $this->assertResponseCode(200);
            $this->assertArrayHasKey('token', $content);
            $client->request(
                'GET',
                '/api/v1/users/current',
                [],
                [],
                ['HTTP_AUTHORIZATION' => 'Bearer ' . $content['token']]
            );
            $this->assertResponseCode(200);
            $content = json_decode($client->getResponse()->getContent(), true);
            $this->assertSame($user['username'], $content['username']);
            $userFromRepository = static::getContainer()->get(UserRepository::class)
                ->findOneBy(['email' => $content['username']]);
            $this->assertSame($userFromRepository->getUserIdentifier(), $content['username']);
            $this->assertSame($userFromRepository->getRoles(), $content['roles']);
            $this->assertEquals($userFromRepository->getBalance(), $content['balance']);
        }
    }

    public function testResfreshSuccessfully()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usernotindb@test.test', 'password' => 'Aa111Bb.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(201);
        $this->assertArrayHasKey('token', $content);
        $this->assertArrayHasKey('refresh_token', $content);
        $client->request(
            'POST',
            '/api/v1/token/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['refresh_token' => $content['refresh_token']])
        );
        $this->assertResponseCode(200);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $content);
        $this->assertArrayHasKey('refresh_token', $content);
    }

    public function testResfreshWithWrongToken()
    {
        $client = $this->getClient();
        $client->request(
            'POST',
            '/api/v1/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'usernotindb@test.test', 'password' => 'Aa111Bb.'])
        );
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertResponseCode(201);
        $this->assertArrayHasKey('token', $content);
        $this->assertArrayHasKey('refresh_token', $content);
        $client->request(
            'POST',
            '/api/v1/token/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['refresh_token' => '123123'])
        );
        $this->assertResponseCode(401);
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('code', $content);
        $this->assertArrayHasKey('message', $content);
        $this->assertEquals('JWT Refresh Token Not Found', $content['message']);
    }
}
