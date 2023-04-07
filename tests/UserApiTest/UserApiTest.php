<?php

use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserApiTest extends \App\Tests\AbstractTest
{
    private array $usersCredentials = [
        ['username' =>'usualuser@study.com', 'password'=> 'user'],
        ['username' =>'admin@study.com', 'password'=> 'admin']
        ];
    protected function getFixtures(): array
    {
        return [new \App\DataFixtures\AppFixtures($this->getContainer()->get(UserPasswordHasherInterface::class))];
    }

    public function testInvalidCredentialsAuth(): void
    {
        $client = $this->getClient();
        $client->request('POST', '/api/v1/auth',[],[], ['CONTENT_TYPE' => 'application/json'],  json_encode(['username'=> '123', 'password' => '123']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $this->assertSame("Invalid credentials.", $arrayedContent['message']);
        $this->assertResponseCode(401);
    }

    public function testValidCredentialsAuth(): void
    {
        $client = $this->getClient();
        foreach ($this->usersCredentials as $user){
            $client->request('POST', '/api/v1/auth',[],[], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username'=> $user['username'], 'password' => $user['password']]));
            $arrayedContent = (array)json_decode($client->getResponse()->getContent());
            $this->assertResponseCode(200);
            $this->assertArrayHasKey('token', $arrayedContent);
        }
    }

    public function testRegisterInvalidEmail():void{
        $client = $this->getClient();
        $client->request('POST', '/api/v1/register',[],[], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username'=> '123', 'password' => 'ASDVsas123.']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $arrayedContent['errors'] = (array)$arrayedContent['errors'];
        $this->assertSame("Ошибка регистрации", $arrayedContent['error_description']);
        $this->assertNotEquals(0, count($arrayedContent['errors']));
        $this->assertArrayHasKey('username', $arrayedContent['errors']);
        $this->assertSame("Поле e-mail содержит некорректные данные.", $arrayedContent['errors']['username']);
    }

    public function testRegisterBlankEmail():void{
        $client = $this->getClient();
        $client->request('POST', '/api/v1/register',[],[], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username'=> '', 'password' => 'ASDVsas123.']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $arrayedContent['errors'] = (array)$arrayedContent['errors'];
        $this->assertSame("Ошибка регистрации", $arrayedContent['error_description']);
        $this->assertNotEquals(0, count($arrayedContent['errors']));
        $this->assertArrayHasKey('username', $arrayedContent['errors']);
        $this->assertSame("Поле e-mail не может быт пустым.", $arrayedContent['errors']['username']);
    }

    public function testRegisterInvalidPassword():void{
        $client = $this->getClient();
        $client->request('POST', '/api/v1/register',[],[], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username'=> 'test@test.test', 'password' => 'dasdadadsa']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $arrayedContent['errors'] = (array)$arrayedContent['errors'];
        $this->assertSame("Ошибка регистрации", $arrayedContent['error_description']);
        $this->assertNotEquals(0, count($arrayedContent['errors']));
        $this->assertArrayHasKey('password', $arrayedContent['errors']);
        $this->assertSame(
            "Пароль должен содержать как один из спец. символов (.!@#$%^&*), прописную и строчные буквы латинского алфавита и цифру.",
            $arrayedContent['errors']['password']);
    }

    public function testRegisterWithPasswordWhichHaveLengthLessThenConstraint():void{
        $client = $this->getClient();
        $client->request('POST', '/api/v1/register',[],[], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username'=> 'test@test.test', 'password' => 'Aa1.']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $arrayedContent['errors'] = (array)$arrayedContent['errors'];
        $this->assertSame("Ошибка регистрации", $arrayedContent['error_description']);
        $this->assertNotEquals(0, count($arrayedContent['errors']));
        $this->assertArrayHasKey('password', $arrayedContent['errors']);
        $this->assertSame(
            "Пароль должен содержать минимум 6 символов.",
            $arrayedContent['errors']['password']);
    }


    public function testSuccessfullyRegister(){
        $client = $this->getClient();
        $client->request('POST', '/api/v1/register',[],[], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username'=> 'usernotindb@test.test', 'password' => 'Aa111Bb.']));
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $this->assertResponseCode(201);
        $this->assertArrayHasKey('token', $arrayedContent);
    }

    public function testGetUserWithUnAuth(){
        $client = $this->getClient();
        $client->request('GET', '/api/v1/users/current');
        $this->assertResponseCode(401);
        $arrayedContent = (array)json_decode($client->getResponse()->getContent());
        $this->assertArrayHasKey('message', $arrayedContent);
        $this->assertSame("JWT Token not found", $arrayedContent['message']);
    }

    public function testGetUserWithAuth(){
        $client = $this->getClient();
        foreach ($this->usersCredentials as $user){
            $client->request('POST', '/api/v1/auth',[],[], ['CONTENT_TYPE' => 'application/json'],
                json_encode(['username'=> $user['username'], 'password' => $user['password']]));
            $arrayedContent = (array)json_decode($client->getResponse()->getContent());
            $this->assertResponseCode(200);
            $this->assertArrayHasKey('token', $arrayedContent);
            $client->request('GET', '/api/v1/users/current',[],[],['HTTP_AUTHORIZATION' => 'Bearer '. $arrayedContent['token']]);
            $this->assertResponseCode(200);
            $arrayedContent = (array)json_decode($client->getResponse()->getContent());
            $this->assertSame($user['username'], $arrayedContent['username']);
            $userFromRepository = static::getContainer()->get(UserRepository::class)
                ->findOneBy(['email' => $arrayedContent['username']]);
            $this->assertSame($userFromRepository->getUserIdentifier(), $arrayedContent['username']);
            $this->assertSame($userFromRepository->getRoles(), $arrayedContent['roles']);
            $this->assertEquals($userFromRepository->getBalance(), $arrayedContent['balance']);
        }
    }
}