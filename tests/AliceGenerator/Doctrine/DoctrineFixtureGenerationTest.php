<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Nelmio\Alice\Loader\NativeLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use Solitus0\AliceGenerator\Exception\UnknownObjectTypeException;
use Solitus0\AliceGenerator\Generator\FixtureGenerationContext;
use Solitus0\AliceGenerator\Generator\FixtureGeneratorBuilder;
use Solitus0\AliceGenerator\MetadataHandler\DoctrineMetadataHandler;
use Solitus0\AliceGenerator\PropertyTransformer\CallbackValueTransformer;
use Solitus0\AliceGenerator\PropertyTransformer\ClassPropertyMatcher;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyMatcher;
use Solitus0\AliceGenerator\PropertyTransformer\PropertyTransformRule;
use Solitus0\AliceGenerator\PropertyTransformer\SimpleValueTransformer;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\Assignee;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\Comment;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\CommentModeration;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\LocalizedLabel;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\Project;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\Task;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\TaskStatus;
use Solitus0\AliceGenerator\Tests\Support\FixtureTestCase;
use Solitus0\AliceGenerator\ValueContext;

final class DoctrineFixtureGenerationTest extends FixtureTestCase
{
    private EntityManager $entityManager;

    private Project $project;

    private Project $otherProject;

    protected function setUp(): void
    {
        $configuration = new Configuration();
        $configuration->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Fixtures']));
        if (PHP_VERSION_ID >= 80400 && (new \ReflectionClass(Configuration::class))->hasMethod('enableNativeLazyObjects')) {
            $configuration->enableNativeLazyObjects(true);
        } else {
            $configuration->setProxyDir(sys_get_temp_dir());
            $configuration->setProxyNamespace('AliceGeneratorTestProxies');
            $configuration->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        }

        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->entityManager = new EntityManager($connection, $configuration);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);

        $seedObjects = (new NativeLoader())->loadFile(__DIR__ . '/Fixtures/seed.yaml')->getObjects();
        foreach ($seedObjects as $seedObject) {
            $this->entityManager->persist($seedObject);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->project = $this->findProject('Fixture export');
        $this->otherProject = $this->findProject('Other project');
        $this->project->runtimeNote = 'Unmapped application state';
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        $this->entityManager->getConnection()->close();
    }

    public function testReloadsDoctrineGraphWithCyclesAndSharedReferences(): void
    {
        $objects = $this->generateAndLoad($this->project, FixtureGenerationContext::create());
        $project = $this->onlyProject($objects);
        [$draft, $design, $implement, $review] = $project->getTasks()->getValues();

        self::assertSame('Fixture export', $project->name);
        self::assertSame('Draft outline', $draft->title);
        self::assertSame(TaskStatus::Draft, $draft->status);
        self::assertSame('Design API', $design->title);
        self::assertEquals(new \DateTimeImmutable('2026-04-21 10:30:00'), $design->dueAt);
        self::assertSame('Implement export', $implement->title);
        self::assertSame('Review', $review->title);
        self::assertSame($project, $design->project);
        self::assertNotNull($design->assignee);
        self::assertSame('Alex', $design->assignee->name);
        self::assertNull($design->assignee->id);
        self::assertSame($design->assignee, $implement->assignee);
        self::assertSame([$design->assignee, $review->assignee], $project->getWatchers()->getValues());
        self::assertCount(2, array_filter($objects, static fn (object $object): bool => $object instanceof Assignee));

        [$comment] = $design->getComments()->getValues();
        self::assertSame(201, $comment->id);
        self::assertSame('Keep references stable', $comment->body);
        self::assertSame($design, $comment->task);

        self::assertNull($project->id);
        self::assertNull($project->summary);
        self::assertSame('', $project->runtimeNote);
    }

    #[DataProvider('metadataOptions')]
    public function testDoctrineOptionsControlGeneratedIdentifiersAndEmptyStrings(
        bool $skipIdentifiers,
        bool $skipEmptyStrings,
    ): void {
        $context = FixtureGenerationContext::create()->setMaximumRecursion(0);
        $objects = $this->generateAndLoad($this->project, $context, $skipIdentifiers, $skipEmptyStrings);
        $project = $this->onlyProject($objects);

        self::assertSame($skipIdentifiers ? null : $this->project->id, $project->id);
        self::assertSame($skipEmptyStrings ? null : '', $project->summary);
        self::assertSame('', $project->runtimeNote);
    }

    public static function metadataOptions(): iterable
    {
        yield 'keep generated identifiers only' => [false, true];
        yield 'keep empty strings only' => [true, false];
    }

    public function testRestoresCompositeIdentifierAndEmbeddedText(): void
    {
        $catalogId = '00000000-0000-4000-8000-000000000003';
        $source = $this->entityManager->find(LocalizedLabel::class, ['catalogId' => $catalogId, 'locale' => 'lt']);
        self::assertNotNull($source);

        $objects = $this->generateAndLoad($source, FixtureGenerationContext::create());
        $label = $objects['LocalizedLabel-1'];

        self::assertInstanceOf(LocalizedLabel::class, $label);
        self::assertSame($catalogId, $label->catalogId);
        self::assertSame('lt', $label->locale);
        self::assertNotNull($label->text);
        self::assertSame('Užduotis', $label->text->caption);
        self::assertSame('Aprašo tekstas', $label->text->description);
    }

    public function testRestoresAnAssociationUsedAsThePrimaryKey(): void
    {
        $source = $this->entityManager->find(CommentModeration::class, ['comment' => 201]);
        self::assertNotNull($source);
        $context = FixtureGenerationContext::create()->setMaximumRecursion(1);
        $objects = $this->generateAndLoad($source, $context);
        $moderation = $objects['CommentModeration-1'];
        $comment = $objects['Comment-1'];

        self::assertInstanceOf(CommentModeration::class, $moderation);
        self::assertInstanceOf(Comment::class, $comment);
        self::assertSame('approved', $moderation->decision);
        self::assertSame(201, $comment->id);
        self::assertSame($comment, $moderation->comment);
    }

    public function testOmitsAMissingDoctrineReferenceEvenWhenIdentifiersAreRetained(): void
    {
        $source = $this->entityManager->getReference(Assignee::class, '00000000-0000-4000-8000-000000000099');
        self::assertNotNull($source);

        self::assertSame([], $this->generateAndLoad($source, FixtureGenerationContext::create(), skipIdentifiers: false));
    }

    public function testLoadsAnUninitializedDoctrineReferenceBeforeExporting(): void
    {
        $source = $this->entityManager->getReference(Assignee::class, '00000000-0000-4000-8000-000000000001');
        self::assertNotNull($source);
        $objects = $this->generateAndLoad($source, FixtureGenerationContext::create());
        $assignee = $objects['Assignee-1'];

        self::assertInstanceOf(Assignee::class, $assignee);
        self::assertSame('Alex', $assignee->name);
    }

    #[DataProvider('recursionDepths')]
    public function testRecursionDepthLimitsWhichRecordsAreExported(int $depth, int $objectCount, int $commentCount): void
    {
        $context = FixtureGenerationContext::create()->setMaximumRecursion($depth);
        $objects = $this->generateAndLoad($this->project, $context);

        self::assertCount($objectCount, $objects);
        self::assertCount($commentCount, array_filter($objects, static fn (object $object): bool => $object instanceof Comment));
    }

    public static function recursionDepths(): iterable
    {
        yield 'project only' => [0, 1, 0];
        yield 'tasks and watchers' => [1, 7, 0];
        yield 'nested comment' => [2, 8, 1];
    }

    public function testObjectConstraintsKeepOnlyTheSelectedProject(): void
    {
        $context = FixtureGenerationContext::create()->addPersistedObjectConstraint($this->project);
        $objects = $this->generateAndLoad([$this->otherProject, $this->project], $context);

        self::assertSame('Fixture export', $this->onlyProject($objects)->name);
        self::assertCount(4, array_filter($objects, static fn (object $object): bool => $object instanceof Task));
    }

    /**
     * @param list<string> $taskTitles
     * @param list<string> $watcherNames
     */
    #[DataProvider('collectionContexts')]
    public function testCollectionOptionsFilterTheExportedGraph(
        FixtureGenerationContext $context,
        array $taskTitles,
        array $watcherNames,
    ): void {
        $objects = $this->generateAndLoad($this->project, $context);
        $project = $this->onlyProject($objects);

        self::assertSame($taskTitles, $project->getTasks()->map(static fn (Task $task): string => $task->title)->getValues());
        self::assertSame($watcherNames, $project->getWatchers()->map(static fn (Assignee $watcher): string => $watcher->name)->getValues());
        self::assertCount(count($taskTitles), array_filter($objects, static fn (object $object): bool => $object instanceof Task));
    }

    public static function collectionContexts(): iterable
    {
        yield 'skip both project collections' => [
            FixtureGenerationContext::create()->addOwnersWithSkippedCollections(Project::class),
            [],
            [],
        ];
        yield 'skip drafts before applying the owner limit' => [
            FixtureGenerationContext::create()
                ->addCollectionItemSkipCondition(
                    static fn (string $owner, object $item): bool => $owner === Project::class
                        && $item instanceof Task
                        && $item->status === TaskStatus::Draft
                )
                ->addCollectionItemLimitPerOwner(Project::class, 2),
            ['Design API', 'Implement export'],
            ['Alex', 'Sam'],
        ];
        yield 'owner limit applies separately to each collection' => [
            FixtureGenerationContext::create()->addCollectionItemLimitPerOwner(Project::class, 1),
            ['Draft outline'],
            ['Alex'],
        ];
        yield 'task class limit leaves watchers intact' => [
            FixtureGenerationContext::create()->addLimitForOwnerItemCollection(Project::class, Task::class, 1),
            ['Draft outline'],
            ['Alex', 'Sam'],
        ];
        yield 'task class and owner limits both apply' => [
            FixtureGenerationContext::create()
                ->addLimitForOwnerItemCollection(Project::class, Task::class, 2)
                ->addCollectionItemLimitPerOwner(Project::class, 1),
            ['Draft outline'],
            ['Alex'],
        ];
        yield 'zero task limit leaves watchers intact' => [
            FixtureGenerationContext::create()->addLimitForOwnerItemCollection(Project::class, Task::class, 0),
            [],
            ['Alex', 'Sam'],
        ];
    }

    public function testTransformsMappedValuesWithoutChangingTheDatabaseObjects(): void
    {
        $context = FixtureGenerationContext::create()
            ->addPropertyTransformRule(new PropertyTransformRule(
                new ClassPropertyMatcher(Assignee::class, 'name'),
                new CallbackValueTransformer(
                    static fn (ValueContext $value): string => $value->getValue() === 'Alex' ? 'Reviewer A' : 'Reviewer B'
                ),
            ))
            ->addPropertyTransformRule(new PropertyTransformRule(
                new PropertyMatcher('name'),
                new SimpleValueTransformer('Redacted project'),
            ))
        ;

        $objects = $this->generateAndLoad($this->project, $context);
        $project = $this->onlyProject($objects);

        self::assertSame('Redacted project', $project->name);
        self::assertSame(['Reviewer A', 'Reviewer B'], $project->getWatchers()->map(
            static fn (Assignee $watcher): string => $watcher->name
        )->getValues());
        self::assertSame(['Alex', 'Sam'], $this->project->getWatchers()->map(
            static fn (Assignee $watcher): string => $watcher->name
        )->getValues());
    }

    public function testRejectsAnUnmappedRootObject(): void
    {
        $generator = FixtureGeneratorBuilder::create()
            ->setMetadataHandler(new DoctrineMetadataHandler($this->entityManager))
            ->build()
        ;

        $this->expectException(UnknownObjectTypeException::class);
        $generator->generate(new \stdClass(), FixtureGenerationContext::create());
    }

    private function findProject(string $name): Project
    {
        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => $name]);
        self::assertNotNull($project);

        return $project;
    }

    /**
     * @param object|list<object> $source
     *
     * @return array<string, object>
     */
    private function generateAndLoad(
        object|array $source,
        FixtureGenerationContext $context,
        bool $skipIdentifiers = true,
        bool $skipEmptyStrings = true,
    ): array {
        $generator = FixtureGeneratorBuilder::create()
            ->setMetadataHandler(new DoctrineMetadataHandler($this->entityManager, $skipIdentifiers, $skipEmptyStrings))
            ->build()
        ;

        return $this->loadFixtures($generator->generate($source, $context));
    }

    /** @param array<string, object> $objects */
    private function onlyProject(array $objects): Project
    {
        $projects = array_values(array_filter($objects, static fn (object $object): bool => $object instanceof Project));
        self::assertCount(1, $projects);

        return $projects[0];
    }
}
