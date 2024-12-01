<?php

use PHPUnit\Framework\TestCase;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Chrome\ChromeOptions;

class UITest extends TestCase
{
    protected $driver;

    protected function setUp(): void
    {
        // Start Chrome WebDriver
        $host = 'http://localhost:4444/wd/hub';

        // Create Chrome options
        $options = new \Facebook\WebDriver\Chrome\ChromeOptions();
        $options->addArguments([
            '--headless',  // Run in headless mode (optional)
            '--no-sandbox',
            '--disable-dev-shm-usage'
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create($host, $capabilities);
    }

    public function testRepositoryListView()
    {
        // Navigate to repositories page
        $this->driver->get('http://localhost/lab1/src/repositories.php');

        // Test search functionality
        $searchField = $this->driver->findElement(WebDriverBy::name('field'));
        $searchField->sendKeys('language');

        $searchInput = $this->driver->findElement(WebDriverBy::name('search'));
        $searchInput->sendKeys('Python');

        $searchButton = $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'));
        $searchButton->click();

        // Verify results contain PHP repositories
        $repositories = $this->driver->findElements(WebDriverBy::cssSelector('.card'));
        $this->assertGreaterThan(0, count($repositories));

        foreach ($repositories as $repo) {
            $language = $repo->findElement(WebDriverBy::cssSelector('.text-muted'))->getText();
            $this->assertStringContainsString('Python', $language);
        }
    }

    public function testRepositoryDetailView()
    {
        // Navigate to a specific repository
        $this->driver->get('http://localhost/lab1/src/repositories.php?repo_id=1');

        // Verify repository details are displayed
        $title = $this->driver->findElement(WebDriverBy::tagName('h1'))->getText();
        $this->assertNotEmpty($title);

        // Test comment functionality for logged in users
        if ($this->isUserLoggedIn()) {
            $comment = "Test comment " . time();
            $commentField = $this->driver->findElement(WebDriverBy::name('comment'));
            $commentField->sendKeys($comment);

            $submitButton = $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'));
            $submitButton->click();

            // Wait for page reload and verify comment appears
            $this->driver->wait(10)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::xpath("//*[contains(text(), '$comment')]")
                )
            );
        }
    }

    protected function isUserLoggedIn()
    {
        try {
            $this->driver->findElement(WebDriverBy::name('comment'));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function tearDown(): void
    {
        // Close browser
        if ($this->driver) {
            $this->driver->quit();
        }
    }
}
