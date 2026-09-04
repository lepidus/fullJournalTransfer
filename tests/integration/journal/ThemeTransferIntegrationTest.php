<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\journal;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PKP\install\Installer;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\tests\DatabaseTestCase;

class ThemeTransferIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $installer = (new \ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
        if (!$installer->installFilterConfig(dirname(__DIR__, 3) . '/filter/filterConfig.xml')) {
            throw new \RuntimeException('Theme transfer filter configuration could not be installed');
        }
    }

    protected function tearDown(): void
    {
        $publicFileManager = new PublicFileManager();
        foreach (array_reverse($this->contexts) as $context) {
            $publicFileManager->rmtree(
                $publicFileManager->getContextFilesPath((int) $context->getId())
            );
            Application::get()->getContextDAO()->deleteObject($context);
        }
        parent::tearDown();
    }

    public function testItTransfersTheSelectedThemeAndItsOptions(): void
    {
        $source = $this->createContext('theme-source-' . bin2hex(random_bytes(4)));
        $source->setData('themePluginPath', 'default');
        Application::get()->getContextDAO()->updateObject($source);
        $this->setRequestContext($source);
        $theme = $this->findTheme('default');
        $theme->init();
        $theme->saveOption('baseColour', '#123456', (int) $source->getId());
        $theme->saveOption('typography', 'lora', (int) $source->getId());
        $theme->saveOption('showDescriptionInJournalIndex', true, (int) $source->getId());

        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('pkp', 'http://pkp.sfu.ca');
        $themeNode = $xpath->query('/pkp:journal/pkp:theme')->item(0);

        $this->assertInstanceOf(DOMElement::class, $themeNode);
        $this->assertSame('default', $themeNode->getAttribute('plugin_path'));
        $this->assertSame('defaultthemeplugin', $themeNode->getAttribute('plugin_name'));
        $this->assertSame(
            ['baseColour' => '#123456', 'showDescriptionInJournalIndex' => true, 'typography' => 'lora'],
            $this->themeOptionsFromXml($xpath)
        );

        $destinationPath = 'theme-destination-' . bin2hex(random_bytes(4));
        $document->documentElement->setAttribute('url_path', $destinationPath);
        $deployment = new FullJournalImportExportDeployment(new Journal(), null);
        $created = $deployment->createContextData($document->documentElement);
        $this->contexts[] = $created;

        $this->assertSame($destinationPath, $created->getPath());
        $this->assertSame('default', $created->getData('themePluginPath'));
        $values = $theme->getOptionValues((int) $created->getId());
        $this->assertSame('#123456', $values['baseColour']);
        $this->assertSame('lora', $values['typography']);
        $this->assertTrue($values['showDescriptionInJournalIndex']);
    }

    public function testItUsesTheDefaultThemeWithoutImportedOptionsWhenTheSelectedThemeIsUnavailable(): void
    {
        $path = 'missing-theme-' . bin2hex(random_bytes(4));
        $source = $this->newContext($path);
        $this->setRequestContext($source);
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $theme = $document->createElementNS('http://pkp.sfu.ca', 'theme');
        $theme->setAttribute('plugin_path', 'unavailable');
        $theme->setAttribute('plugin_name', 'unavailablethemeplugin');
        $option = $document->createElementNS('http://pkp.sfu.ca', 'option', '"#abcdef"');
        $option->setAttribute('name', 'baseColour');
        $theme->appendChild($option);
        $settings = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'context_settings')->item(0);
        $settings->parentNode->insertBefore($theme, $settings->nextSibling);
        $deployment = new FullJournalImportExportDeployment(new Journal(), null);

        $created = $deployment->createContextData($document->documentElement);
        $this->contexts[] = $created;

        $this->assertSame($path, $created->getPath());
        $this->assertSame('default', $created->getData('themePluginPath'));
        $this->assertSame(1, DB::table('plugin_settings')
            ->where('context_id', $created->getId())
            ->where('plugin_name', 'defaultthemeplugin')
            ->where('setting_name', 'enabled')
            ->where('setting_value', '1')
            ->count());
        $this->assertSame(0, DB::table('plugin_settings')
            ->where('context_id', $created->getId())
            ->where('plugin_name', 'defaultthemeplugin')
            ->where('setting_name', '!=', 'enabled')
            ->count());
    }

    private function createContext(string $path): Journal
    {
        $context = $this->newContext($path);
        Application::get()->getContextDAO()->insertObject($context);
        $this->contexts[] = $context;
        return $context;
    }

    private function newContext(string $path): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath($path);
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        $context->setSequence(1);
        $context->setData('supportedLocales', ['en']);
        $context->setData('supportedFormLocales', ['en']);
        $context->setData('supportedSubmissionLocales', ['en']);
        $context->setData('name', ['en' => 'Theme Transfer Journal']);
        $context->setData('contactName', 'Editorial Team');
        $context->setData('contactEmail', 'editor@example.com');
        return $context;
    }

    private function findTheme(string $path): ThemePlugin
    {
        foreach (PluginRegistry::loadCategory('themes', false) as $theme) {
            if ($theme instanceof ThemePlugin && $theme->getDirName() === $path) {
                return $theme;
            }
        }
        throw new \RuntimeException('Required test theme is not installed: ' . $path);
    }

    private function setRequestContext(Journal $context): void
    {
        $router = new class ($context) extends \APP\core\PageRouter {
            private Journal $context;

            public function __construct(Journal $context)
            {
                $this->context = $context;
            }

            public function getContext($request, $forceReload = false): Journal
            {
                return $this->context;
            }
        };
        Application::get()->getRequest()->setRouter($router);
    }

    private function themeOptionsFromXml(DOMXPath $xpath): array
    {
        $options = [];
        foreach ($xpath->query('/pkp:journal/pkp:theme/pkp:option') as $option) {
            $options[$option->getAttribute('name')] = json_decode($option->textContent, true, 512, JSON_THROW_ON_ERROR);
        }
        ksort($options);
        return $options;
    }
}
