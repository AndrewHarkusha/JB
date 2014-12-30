<?php

namespace App\JoboardBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;

class JobControllerTest extends WebTestCase
{
    private $em;
    private $application;

    public function setUp()
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        $this->application = new Application(static::$kernel);

        // удаляем базу
        $command = new DropDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:drop',
            '--force' => true
        ));
        $command->run($input, new NullOutput());

        // закрываем соединение с базой
        $connection = $this->application->getKernel()->getContainer()->get('doctrine')->getConnection();
        if ($connection->isConnected()) {
            $connection->close();
        }

        // создаём базу
        $command = new CreateDatabaseDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:database:create',
        ));
        $command->run($input, new NullOutput());

        // создаём структуру
        $command = new CreateSchemaDoctrineCommand();
        $this->application->add($command);
        $input = new ArrayInput(array(
            'command' => 'doctrine:schema:create',
        ));
        $command->run($input, new NullOutput());

        // получаем Entity Manager
        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        // загружаем фикстуры
        $client = static::createClient();
        $loader = new \Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader($client->getContainer());
        $loader->loadFromDirectory(static::$kernel->locateResource('@JoboardBundle/DataFixtures/ORM'));
        $purger = new \Doctrine\Common\DataFixtures\Purger\ORMPurger($this->em);
        $executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($this->em, $purger);
        $executor->execute($loader->getFixtures());
    }

    public function testIndex()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/job/');

        $this->assertEquals('App\JoboardBundle\Controller\JobController::indexAction', $client->getRequest()->attributes->get('_controller'));
        $this->assertTrue($crawler->filter('.jobs td.position:contains("Expired")')->count() == 0);

        $kernel = static::createKernel();
        $kernel->boot();

        // Находим категорию "дизайн"
        $categoryDesign = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('dizajn');

        // Находим категорию "программирование"
        $categoryProgramming = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('programmirovanie');

        $maxJobsOnHomepage = $kernel->getContainer()->getParameter('max_jobs_on_homepage');
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' tr')->count() <= $maxJobsOnHomepage);

        $this->assertTrue($crawler->filter('.category' . $categoryDesign->getId() . ' .more-jobs')->count() == 0);
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' .more-jobs')->count() == 1);

        $job = $this->getMostRecentProgrammingJob();
        $this->assertTrue($crawler->filter('.category-' . $categoryProgramming->getId() . ' tr')->first()->filter(sprintf('a[href*="/%d/"]', $job->getId()))->count() == 1);

        $link = $crawler->selectLink('Web Разработчик')->first()->link();
        $client->click($link);
        $this->assertEquals('App\JoboardBundle\Controller\JobController::showAction', $client->getRequest()->attributes->get('_controller'));

        $this->assertEquals($job->getCompanySlug(), $client->getRequest()->attributes->get('company'));
        $this->assertEquals($job->getLocationSlug(), $client->getRequest()->attributes->get('location'));
        $this->assertEquals($job->getPositionSlug(), $client->getRequest()->attributes->get('position'));
        $this->assertEquals($job->getId(), $client->getRequest()->attributes->get('id'));

        // Несуществующая вакансия должна вести на страницу 404
        $client->request('GET', '/job/nocompany/nolocation/0/nodeveloper');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [404, 301]));

        // Завершённая вакансия должна вести на страницу 404
        $client->request('GET', sprintf('job/company-100/moscow-russia/%d/web-developer/', $this->getExpiredJob()->getId()));
        $this->assertTrue(404 === $client->getResponse()->getStatusCode());
    }

    public function getMostRecentProgrammingJob()
    {
        $categoryProgramming = $this->em->getRepository('JoboardBundle:Category')->findOneBySlug('programmirovanie');
        $query = $this->em->createQuery('SELECT j from AppJoboardBundle:Job j
                                   LEFT JOIN j.category c
                                   WHERE c.slug = :slug AND j.expires_at > :date
                                   ORDER BY j.created_at DESC');
        $query->setParameter('slug', $categoryProgramming->getSlug());
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }

    public function getExpiredJob()
    {
        $query = $this->em->createQuery('SELECT j from JoboardBundle:Job j WHERE j.expires_at < :date');
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);

        return $query->getSingleResult();
    }


    public function testJobForm()
{
    $client  = static::createClient();
    $crawler = $client->request('GET', '/job/new');


    $this->assertEquals('App\JoboardBundle\Controller\JobController::newAction', $client->getRequest()->attributes->get('_controller'));

    $form = $crawler->selectButton('Просмотр')->form([
            'app_joboardbundle_job[company]'      => 'Test compamy',
            'app_joboardbundle_job[url]'          => 'http://www.example.com/'
            //'app_joboardbundle_job[file]'  => __DIR__.'/../../../../../web/bundles/joboard/images/joboard.png',
            'app_joboardbundle_job[position]'     => 'Разработчик',
            'app_joboardbundle_job[location]'     => 'Москва, Россия',
            'app_joboardbundle_job[description]'  => 'Хорошее знание Symfony2',
            'app_joboardbundle_job[how_to_apply]' => 'Резюме на электронную почту',
            'app_joboardbundle_job[email]'        => 'job@example.com',
            'app_joboardbundle_job[is_public]'    => false,
   ]);

    $client->submit($form);
    $this->assertEquals('App\JoboardBundle\Controller\JobController::createAction', $client->getRequest()->attributes->get('_controller'));
    $client->followRedirect();
    $this->assertEquals('App\JoboardBundle\Controller\JobController::previewAction', $client->getRequest()->attributes->get('_controller'));

    $kernel = static::createKernel();
    $kernel->boot();
    $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');


    $query = $em->createQuery('SELECT count(j.id) from AppJoboardBundle:Job j
                                   WHERE j.location = :location
                                   AND (j.is_activated = 0 OR j.is_activated IS NULL)
                                   AND j.is_public = 0');
    $query->setParameter('location', 'Москва, Россия');
    $this->assertTrue((bool) $query->getSingleScalarResult());

   $crawler = $client->request('GET', '/job/new');
        $form = $crawler->selectButton('Просмотр')->form([
            'app_joboardbundle_job[company]'      => 'Test compamy',
            'app_joboardbundle_job[position]'     => 'Разработчик',
            'app_joboardbundle_job[location]'     => 'Москва, Россия',
            'app_joboardbundle_job[email]'        => 'not.an.email',
    ]);
    $crawler = $client->submit($form);

    // Проверка количества ошибок
   $this->assertTrue($crawler->filter('.error_list')->count() == 3);
   $this->assertTrue($crawler->filter('#app_joboardbundle_job_description')->siblings()->first()->filter('.error_list')->count() == 1);
   $this->assertTrue($crawler->filter('#app_joboardbundle_job_how_to_apply')->siblings()->first()->filter('.error_list')->count() == 1);
   $this->assertTrue($crawler->filter('#app_joboardbundle_job_email')->siblings()->first()->filter('.error_list')->count() == 1);

}


    public function createJob($values = [])
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/job/new');
        $form    = $crawler->selectButton('Отправить')->form(array_merge([
            'app_joboardbundle_job[company]'      => 'Test company',
            'app_joboardbundle_job[url]'          => 'http://www.example.com/',
            'app_joboardbundle_job[position]'     => 'Разработчик',
            'app_joboardbundle_job[location]'     => 'Москва, Россия',
            'app_joboardbundle_job[description]'  => 'Хорошее знание Symfony2',
            'app_joboardbundle_job[how_to_apply]' => 'Резюме на электронную почту',
            'app_joboardbundle_job[email]'        => 'job@example.com',
            'app_joboardbundle_job[is_public]'    => false,
        ], $values));

        $client->submit($form);
        $client->followRedirect();

        return $client;
    }


    public function testPublishJob()
    {
        $client  = $this->createJob(['app_joboardbundle_job[position]' => 'FOO1']);
        $crawler = $client->getCrawler();
        $form = $crawler->selectButton('Опубликовать')->form();
        $client->submit($form);

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $query = $em->createQuery('SELECT count(j.id) from AppJoboardBundle:Job j
                                   WHERE j.position = :position AND j.is_activated = 1');
        $query->setParameter('position', 'FOO1');
        $this->assertTrue((bool) $query->getSingleScalarResult());
    }

    public function testDeleteJob()
    {
        $client  = $this->createJob(['app_joboardbundle_job[position]' => 'FOO2']);
        $crawler = $client->getCrawler();
        $form    = $crawler->selectButton('Удалить')->form();
        $client->submit($form);

        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT count(j.id) from AppJoboardBundle:Job j
                                   WHERE j.position = :position');
        $query->setParameter('position', 'FOO2');
        $this->assertTrue(0 == $query->getSingleScalarResult());
    }

    public function createJob($values = [], $publish = false)
    {
        $client  = static::createClient();
        $crawler = $client->request('GET', '/job/new');
        $form    = $crawler->selectButton('Отправить')->form(array_merge([
            'app_joboardbundle_job[company]'      => 'Test company',
            'app_joboardbundle_job[url]'          => 'http://www.example.com/',
            'app_joboardbundle_job[position]'     => 'Разработчик',
            'app_joboardbundle_job[location]'     => 'Москва, Россия',
            'app_joboardbundle_job[description]'  => 'Хорошее знание Symfony2',
            'app_joboardbundle_job[how_to_apply]' => 'Резюме на электронную почту',
            'app_joboardbundle_job[email]'        => 'job@example.com',
            'app_joboardbundle_job[is_public]'    => false,
        ], $values));

        $client->submit($form);
        $client->followRedirect();

        if ($publish) {
            $crawler = $client->getCrawler();
            $form = $crawler->selectButton('Опубликовать')->form();
            $client->submit($form);
            $client->followRedirect();
        }

        return $client;
    }

    public function getJobByPosition($position)
    {
        $kernel = static::createKernel();
        $kernel->boot();
        $em = $kernel->getContainer()->get('doctrine.orm.entity_manager');

        $query = $em->createQuery('SELECT j from AppJoboardBundle:Job j WHERE j.position = :position');
        $query->setParameter('position', $position);
        $query->setMaxResults(1);
        return $query->getSingleResult();
    }

    public function testEditJob()
    {
        $client = $this->createJob(['app_joboardbundle_job[position]' => 'FOO3'], true);
        $client->getCrawler();
        $client->request('GET', sprintf('/job/%s/edit', $this->getJobByPosition('FOO3')->getToken()));
        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

}
