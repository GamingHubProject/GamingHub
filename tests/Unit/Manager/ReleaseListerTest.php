<?php

namespace Tests\Unit\Manager;

use App\Manager\ExtensionDefinition;
use App\Manager\ReleaseLister;
use Tests\TestCase;
use Tests\Unit\Manager\Support\FakeHttpClient;

class ReleaseListerTest extends TestCase
{
    private ExtensionDefinition $extension;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = ExtensionDefinition::fromArray([
            'id' => 'basic-connectors',
            'name' => 'Basic Connectors',
            'repository' => 'https://github.com/GamingHubProject/BasicConnectors',
            'release_asset' => 'basic-connectors-*.zip',
        ]);
    }

    public function test_lists_versions_newest_first_with_the_leading_v_stripped(): void
    {
        $fake = new FakeHttpClient;
        $fake->respond('https://api.github.com/repos/GamingHubProject/BasicConnectors/releases', json_encode([
            ['tag_name' => 'v0.1.3', 'draft' => false],
            ['tag_name' => 'v0.1.2', 'draft' => false],
            ['tag_name' => 'v0.1.1', 'draft' => false],
        ]));

        $versions = (new ReleaseLister($fake))->listVersions($this->extension);

        $this->assertSame(['0.1.3', '0.1.2', '0.1.1'], $versions);
    }

    public function test_excludes_draft_releases(): void
    {
        $fake = new FakeHttpClient;
        $fake->respond('https://api.github.com/repos/GamingHubProject/BasicConnectors/releases', json_encode([
            ['tag_name' => 'v0.2.000-rc', 'draft' => true],
            ['tag_name' => 'v0.1.3', 'draft' => false],
        ]));

        $versions = (new ReleaseLister($fake))->listVersions($this->extension);

        $this->assertSame(['0.1.3'], $versions);
    }

    public function test_returns_an_empty_list_rather_than_throwing_when_github_is_unreachable(): void
    {
        $fake = new FakeHttpClient; // no response configured -> throws internally

        $versions = (new ReleaseLister($fake))->listVersions($this->extension);

        $this->assertSame([], $versions);
    }

    public function test_returns_an_empty_list_for_a_non_github_repository_url(): void
    {
        $fake = new FakeHttpClient;
        $extension = ExtensionDefinition::fromArray([
            'id' => 'weird',
            'name' => 'Weird',
            'repository' => 'https://example.com',
            'release_asset' => 'weird-*.zip',
        ]);

        $versions = (new ReleaseLister($fake))->listVersions($extension);

        $this->assertSame([], $versions);
    }
}
