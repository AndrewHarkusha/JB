<?php

# src/App/JoboardBundle/Tests/Repository/JobRepositoryTest.php

namespace App\JoboardBundle\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Doctrine\Bundle\DoctrineBundle\Command\DropDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\CreateDatabaseDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\CreateSchemaDoctrineCommand;

class JobRepositoryTest extends WebTestCase
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

    public function testCountActiveJobs()
    {
        $query = $this->em->createQuery('SELECT c FROM JoboardBundle:Category c');
        $categories = $query->getResult();

        foreach($categories as $category) {
            $query = $this->em->createQuery('SELECT COUNT(j.id) FROM JoboardBundle:Job j
                                             WHERE j.category = :category AND j.expires_at > :date');
            $query->setParameter('category', $category->getId());
            $query->setParameter('date', date('Y-m-d H:i:s', time()));
            $jobsDb = $query->getSingleScalarResult();
            $jobsRep = $this->em->getRepository('JoboardBundle:Job')->countActiveJobs($category->getId());
            // Этот тест проверяет сравнивает количество активных вакансий из репозитория
            // и количество активных ваканий из базы
            $this->assertEquals($jobsRep, $jobsDb);
        }
    }

    public function testGetActiveJobs()
    {
        $query = $this->em->createQuery('SELECT c from JoboardBundle:Category c');
        $categories = $query->getResult();

        foreach ($categories as $category) {
            $query = $this->em->createQuery('SELECT COUNT(j.id) from JoboardBundle:Job j
                                             WHERE j.expires_at > :date AND j.category = :category');
            $query->setParameter('date', date('Y-m-d H:i:s', time()));
            $query->setParameter('category', $category->getId());
            $jobsDb = $query->getSingleScalarResult();

            $jobs_rep = $this->em->getRepository('JoboardBundle:Job')->getActiveJobs($category->getId(), null, null);
            // Проверят количество активных вакансий в категории на соотвествие
            // количеству активных вакансий в базе
            $this->assertEquals($jobsDb, count($jobs_rep));

          // Если в категории хотя бы 3 активные вакансии, то также проверяем параметры $max и $offset
         if($jobsDb > 2 ) {
        $jobsRep = $this->em->getRepository('JoboardBundle:Job')->getActiveJobs($category->getId(), 2);

         // Этот тест проверяем, что действительно было выбрано только 2 активных вакансии
        $this->assertEquals(2, count($jobsRep));

         $jobsRep = $this->em->getRepository('JoboardBundle:Job')->getActiveJobs($category->getId(), 2, 1);

          // Мы установили лимит на 2 результата, и смещение со второй вакансии
         $this->assertEquals(2, count($jobsRep));
    }
}
        }
    }

    public function testGetActiveJob()
    {
        $query = $this->em->createQuery('SELECT j FROM JoboardBundle:Job j
                                         WHERE j.expires_at > :date');
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);
        $jobDb = $query->getSingleResult();

        $jobRep = $this->em->getRepository('JoboardBundle:Job')->getActiveJob($jobDb->getId());
        // Если вакансия активна, то метод getActiveJob() должен вернуть не null значение
        $this->assertNotNull($jobRep);

        $query = $this->em->createQuery('SELECT j FROM JoboardBundle:Job j
                                         WHERE j.expires_at < :date');
        $query->setParameter('date', date('Y-m-d H:i:s', time()));
        $query->setMaxResults(1);
        $jobExpired = $query->getSingleResult();

        $jobRep = $this->em->getRepository('JoboardBundle:Job')->getActiveJob($jobExpired->getId());
        // Если вакансия завершена, то метод getActiveJob() должен вернуть значение null
        $this->assertNull($jobRep);
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->em->close();
    }
}
