<?php

declare(strict_types=1);

namespace QUITests\Feed;

use QUI\Feed\Handler\GoogleSitemap\Feed as GoogleSitemapFeed;
use QUI\Projects\Project;
use RuntimeException;

class TestGoogleSitemapFeed extends GoogleSitemapFeed
{
    /** @var array<int, string> */
    private array $languages = [];

    /** @var array<string, Project> */
    private array $projects = [];

    /**
     * @param array<int, string> $languages
     * @param array<string, Project> $projects
     */
    public function setTestProjects(array $languages, array $projects): self
    {
        $this->languages = $languages;
        $this->projects = $projects;

        return $this;
    }

    protected function getVhostLanguages(Project $Project): array
    {
        return $this->languages;
    }

    protected function getLanguageProject(string $projectName, string $language): Project
    {
        if (!isset($this->projects[$language])) {
            throw new RuntimeException('Missing test project for language ' . $language);
        }

        return $this->projects[$language];
    }
}
