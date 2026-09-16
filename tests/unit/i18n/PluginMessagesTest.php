<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\i18n;

use APP\plugins\importexport\fullJournalTransfer\package\ArchiveManager;
use APP\plugins\importexport\fullJournalTransfer\policy\JournalLocalePolicy;
use APP\plugins\importexport\fullJournalTransfer\policy\UserIdentityPolicy;
use APP\plugins\importexport\fullJournalTransfer\validation\PackageReferenceValidator;
use DOMDocument;
use Gettext\Loader\PoLoader;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PKP\i18n\interfaces\LocaleInterface;
use PKP\i18n\Locale;

class PluginMessagesTest extends TestCase
{
    private $previousContainer;
    private $previousFacadeApplication;
    private string $locale = 'pt_BR';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        // Isolate the selected language and cache eligibility from site/database configuration.
        // Translation loading, interpolation and __() use the real PKP implementation.
        $translator = $this->getMockBuilder(Locale::class)->onlyMethods(['getLocale', 'isSupported'])->getMock();
        $translator->method('getLocale')->willReturnCallback(fn () => $this->locale);
        $translator->method('isSupported')->willReturn(false);
        $translator->registerPath(dirname(__DIR__, 3) . '/locale');
        $container = new Container();
        $container->instance('translator', $translator);
        $container->instance(LocaleInterface::class, $translator);
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstance(LocaleInterface::class);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstance(LocaleInterface::class);
        parent::tearDown();
    }

    public function testItTranslatesValidationErrorsAndPreservesXmlReferences(): void
    {
        $document = new DOMDocument();
        $document->loadXML("<reference_data>\n<review_forms><review_form/></review_forms>\n</reference_data>");
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'O atributo source_ref não foi informado no elemento de dados de referência “review_form”, na linha 2.'
        );

        (new PackageReferenceValidator())->validateReferenceData($document->documentElement);
    }

    /** @dataProvider primaryLocaleMessages */
    public function testItUsesTheActiveLocaleForPolicyErrors(string $locale, string $message): void
    {
        $this->locale = $locale;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new JournalLocalePolicy())->resolve(['pt_BR'], ['pt_BR'], ['pt_BR'], 'pt_BR', ['en']);
    }

    public static function primaryLocaleMessages(): array
    {
        return [
            ['pt_BR', 'O idioma principal da revista (pt_BR) não está disponível no OJS de destino.'],
            ['en', 'The journal primary locale (pt_BR) is not available in the destination OJS.'],
        ];
    }

    public function testItIdentifiesTheUserInTheTranslatedEmailError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'O usuário “joao” não pôde ser importado porque seu endereço de e-mail está vazio ou é inválido.'
        );

        (new UserIdentityPolicy())->resolve('', 'joao', static fn () => null, static fn () => false);
    }

    public function testItTranslatesPackageProgressAndEarlyValidationErrors(): void
    {
        $progress = [];
        try {
            (new ArchiveManager())->withExtractedPackage(
                __DIR__,
                '3.4.0.10',
                static function (): void {
                    self::fail('An invalid package must not reach the importer');
                },
                static function (string $message) use (&$progress): void {
                    $progress[] = $message;
                }
            );
            $this->fail('A directory must be rejected as a package');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('O pacote deve ser um arquivo regular.', $exception->getMessage());
        }
        $this->assertSame(['Validando o pacote da revista...'], $progress);
    }

    public function testAllPluginTranslationsHaveMatchingParametersAndCanBeRendered(): void
    {
        $loader = new PoLoader();
        $english = $loader->loadFile(dirname(__DIR__, 3) . '/locale/en/locale.po');
        $portuguese = $loader->loadFile(dirname(__DIR__, 3) . '/locale/pt_BR/locale.po');
        $this->assertCount(count($english), $portuguese);
        foreach ($english as $translation) {
            $key = $translation->getOriginal();
            $translated = $portuguese->find(null, $key);
            $this->assertNotNull($translated, $key);
            $this->assertNotSame('', $translated->getTranslation(), $key);
            preg_match_all('/\{\$(\w+)\}/', $translation->getTranslation(), $englishParameters);
            preg_match_all('/\{\$(\w+)\}/', $translated->getTranslation(), $portugueseParameters);
            sort($englishParameters[1]);
            sort($portugueseParameters[1]);
            $this->assertSame($englishParameters[1], $portugueseParameters[1], $key);
            $parameters = array_fill_keys($englishParameters[1], 'example');
            foreach (['en', 'pt_BR'] as $locale) {
                $message = __($key, $parameters, $locale);
                $this->assertStringNotContainsString('##', $message, $key);
                $this->assertStringNotContainsString('{$', $message, $key);
            }
        }
    }
}
