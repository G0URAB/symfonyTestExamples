<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $passwordEncoder;

    public function setUp(): void
    {
        $this->client = static::createClient();
        parent::setUp(); // TODO: Change the autogenerated stub
        self::bootKernel();
        $container = self::$container;
        $this->entityManager = $container
            ->get('doctrine')
            ->getManager();
        $this->passwordEncoder = $container->get("security.user_password_encoder.generic");
        $crawler= $this->client->request('GET', '/');
        $this->makeSureDatabaseIsEmpty();
    }

    public function testUnauthorizedLogin()
    {
        $this->client->request('GET', '/dashboard');
        $this->assertFalse($this->client->getResponse()->isSuccessful());
    }

    /**
     * @dataProvider
     */
    public function testPictureUpload()
    {
        //Do a fast-login
        $user1=$this->createTestUser();
        $this->client->loginUser($user1);
        $oldPicture = $user1->getProfilePicture();

        /* 3 things to test when uploading a picture or a file
          1st : A successful response from end-point
          2nd: The respective entity has stored file's name in database
          3rd: The file/ picture exists in the expected folder
         These 3 tests should be executed in chronological order so that we can
         find out which failure is actually responsible if something goes wrong.
        */

        $crawler = $this->client->request('GET','/dashboard');
        $form = $crawler->selectButton('Submit')->form();

        //These form-fields are mandatory fields and thats why we need set them
        $form["profile[firstName]"]->setValue('Gourab');
        $form["profile[lastName]"]->setValue('Sahu');
        $form["profile[gender]"]->setValue(0);
        $form["profile[age]"]->setValue(31);
        $form["profile[profilePicture]"]->upload($this->client->getKernel()->getProjectDir().DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."images".
            DIRECTORY_SEPARATOR."test_profile".DIRECTORY_SEPARATOR."mickey.jpg");
        $this->client->submit($form);

        //1st test check response
        $this->assertResponseRedirects();

        //2nd test confirm the file has been changed in database
        $this->entityManager->refresh($user1);
        $newPicture = $user1->getProfilePicture();

        $this->assertFalse($oldPicture==$newPicture);

        //3rd test new file's existence in expected folder
        $this->assertTrue(file_exists($this->client->getKernel()->getProjectDir().DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."images".
            DIRECTORY_SEPARATOR."profile".DIRECTORY_SEPARATOR.$newPicture));
        $this->deleteTestUser($user1);
    }


    public function testXmlHttpRequest()
    {
        $this->createTestUser();
        $user1 = self::$container->get(UserRepository::class)->findOneByEmail("grv_sh@yahoo.co.in");
        $this->client->loginUser($user1);
        $id = $user1->getId();
        $this->client->xmlHttpRequest("POST","/whoami",['id'=>$id]);
        $data = json_decode($this->client->getResponse()->getContent(),true);

        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertTrue($data['firstName']=="Gourab");

        //Logout user and check if getting an error
        $this->client->request("GET","/logout");
        $this->client->xmlHttpRequest("POST","/whoami",['id'=>4]);
        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
        $data = json_decode($this->client->getResponse()->getContent(),true);
        $this->assertArrayHasKey('error',$data);

        //Remove the user after successful tests
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
        $user->setFirstName("Gourab");
        $user->setLastName("Sahu");
        $user->setGender("Male");
        $user->setAge("31");
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function deleteTestUser($user)
    {
        $picture = $this->client->getKernel()->getProjectDir().DIRECTORY_SEPARATOR."public".DIRECTORY_SEPARATOR."images".
            DIRECTORY_SEPARATOR."profile".DIRECTORY_SEPARATOR.$user->getProfilePicture();
        if($user->getProfilePicture())
        {
            chmod($picture, 0644);
            unlink($picture);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function makeSureDatabaseIsEmpty()
    {
        $users =$this->entityManager->getRepository(User::class)->findAll();
        foreach($users as $user)
        {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // doing this is recommended to avoid memory leaks
        $this->entityManager->close();
        $this->entityManager = null;
    }

}