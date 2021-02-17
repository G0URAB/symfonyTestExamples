<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    private $client;
    private $crawler;
    private $form;
    private $entityManager;
    private $passwordEncoder;

    public function setUp()
    {
        $this->client = static::createClient();
        parent::setUp(); // TODO: Change the autogenerated stub
        self::bootKernel();
        $container = self::$container;
        $this->entityManager = $container
            ->get('doctrine')
            ->getManager();
        $this->passwordEncoder = $container->get("security.user_password_encoder.generic");
        $this->crawler= $this->client->request('GET', '/login');
        $this->form = $this->crawler->selectButton('Sign in')->form();
    }

    /**
     * @dataProvider loginCredentials
     * @param $username
     * @param $password
     */
    public function testLogin($username,$password)
    {
        $this->createTestUser();

        //Test login with dataProvider option
        $this->form['email']->setValue($username);
        $this->form['password']->setValue($password);
        $this->client->submit($this->form);
        $this->assertResponseRedirects("/index");

        //or
        /*$this->client->request('GET','/dashboard');
        $this->assertResponseIsSuccessful();*/
    }

    public function loginCredentials()
    {
        return [
            ['grv_sh@yahoo.co.in', 'morePizza'],
            /*['grv_sh@rediffmail.com','mangoDrops']*/
        ];
    }

    public function testFastLogin()
    {
        /* Fast login is used in a situation, where logging in user is not first priority
        but implementing functional tests in a protected-page. Fast login can be used to reduce time
        for the time-taking normal login process. */

        $user1 = static::$container->get(UserRepository::class)->findOneByEmail("grv_sh@yahoo.co.in");
        $this->client->loginUser($user1);
        $this->client->request('GET','/dashboard');
        $this->assertResponseIsSuccessful();

        $this->deleteTestUser($user1);
    }

    public function createTestUser()
    {
        $user = new User();
        $user->setEmail("grv_sh@yahoo.co.in");
        $user->setPassword(
            $this->passwordEncoder->encodePassword(
                $user,
                'morePizza'
            )
        );
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function deleteTestUser($user)
    {
        if($user->getProfilePicture())
         unlink($this->client->getKernel()->getProjectDir()."/public/images/profile/".$user->getProfilePicture());
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}