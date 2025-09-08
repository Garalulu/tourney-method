# Testing Strategy

## Testing Pyramid
```
           E2E Tests
          /        \
        Integration Tests  
       /            \
   Frontend Unit  Backend Unit
```

## Test Organization

### Frontend Tests
```
tests/frontend/
├── unit/
│   ├── components/
│   │   ├── TournamentCard.test.js
│   │   ├── FilterPanel.test.js
│   │   └── ModalViewer.test.js
│   ├── services/
│   │   ├── ApiClient.test.js
│   │   └── FilterService.test.js
│   └── utils/
│       ├── KoreanUtils.test.js
│       └── DateUtils.test.js
├── integration/
│   ├── tournament-list.test.js
│   ├── filter-functionality.test.js
│   └── modal-interactions.test.js
└── fixtures/
    ├── tournament-data.json
    └── korean-text-samples.json
```

### Backend Tests
```
tests/backend/
├── unit/
│   ├── services/
│   │   ├── TournamentParserTest.php
│   │   ├── AuthServiceTest.php
│   │   └── TermMappingServiceTest.php
│   ├── repositories/
│   │   ├── TournamentRepositoryTest.php
│   │   └── TermMappingRepositoryTest.php
│   └── utils/
│       ├── ValidationHelperTest.php
│       └── DateHelperTest.php
├── integration/
│   ├── DatabaseIntegrationTest.php
│   ├── OAuthIntegrationTest.php
│   └── ParserIntegrationTest.php
└── fixtures/
    ├── sample_forum_posts.html
    ├── korean_tournament_posts.html
    └── test_database.db
```

### E2E Tests
```
tests/e2e/
├── user-flows/
│   ├── tournament-discovery.test.js
│   ├── admin-workflow.test.js
│   └── korean-language-support.test.js
├── cross-browser/
│   ├── chrome.config.js
│   ├── firefox.config.js
│   └── safari.config.js
└── performance/
    ├── page-load-times.test.js
    └── api-response-times.test.js
```

## Test Examples

### Frontend Component Test
```javascript
// TournamentCard.test.js
describe('TournamentCard', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="tournament-123" class="tournament-card">
                <h3 class="tournament-title">Korean Spring Tournament</h3>
                <p class="tournament-host">TestHost</p>
            </div>
        `;
    });

    test('should initialize with Korean text support', () => {
        const card = TournamentCard.init('#tournament-123');
        expect(card.options.supportKorean).toBe(true);
    });

    test('should handle Korean title click correctly', () => {
        const card = TournamentCard.init('#tournament-123');
        const titleElement = document.querySelector('.tournament-title');
        
        titleElement.click();
        
        expect(window.open).toHaveBeenCalledWith(
            expect.stringContaining('osu.ppy.sh'),
            '_blank'
        );
    });

    test('should open modal on card click', () => {
        const card = TournamentCard.init('#tournament-123');
        const cardElement = document.querySelector('.tournament-card');
        
        cardElement.click();
        
        expect(ModalViewer.open).toHaveBeenCalledWith(123);
    });
});
```

### Backend API Test
```php
<?php
// TournamentApiTest.php
class TournamentApiTest extends PHPUnit\Framework\TestCase {
    private $api;
    private $repository;

    protected function setUp(): void {
        $this->repository = $this->createMock(TournamentRepository::class);
        $this->api = new TournamentController($this->repository);
    }

    public function testGetTournamentsReturnsKoreanOptimizedData() {
        // Arrange
        $tournaments = [
            [
                'id' => 1,
                'title' => '한국 스프링 토너먼트',
                'created_at' => '2025-09-05 14:30:00',
                'language_detected' => 'ko'
            ]
        ];
        
        $this->repository->expects($this->once())
            ->method('findByFilters')
            ->willReturn($tournaments);

        // Act
        ob_start();
        $this->api->api();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        // Assert
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['tournaments']);
        $this->assertStringContains('한국 스프링', $data['tournaments'][0]['title']);
        $this->assertNotEmpty($data['tournaments'][0]['created_at_kst']);
    }

    public function testFilterByKoreanText() {
        // Test Korean text filtering functionality
        $_GET['search'] = '한국';
        
        $this->repository->expects($this->once())
            ->method('findByFilters')
            ->with($this->callback(function($filters) {
                return $filters['search'] === '한국';
            }))
            ->willReturn([]);

        ob_start();
        $this->api->api();
        $response = ob_get_clean();
        $data = json_decode($response, true);

        $this->assertTrue($data['success']);
    }
}
?>
```

### E2E Test
```javascript
// tournament-discovery-flow.test.js
describe('Korean Tournament Discovery Flow', () => {
    test('User can discover Korean tournaments end-to-end', async () => {
        // Navigate to homepage
        await page.goto('http://localhost:8000');
        
        // Verify Korean language support
        await expect(page).toHaveTitle(/osu! Tournament Discovery/);
        
        // Check featured tournaments section
        const featuredTournaments = await page.locator('.featured-tournaments .tournament-card');
        await expect(featuredTournaments).toHaveCount(3);
        
        // Navigate to all tournaments
        await page.click('text=View All Tournaments');
        await expect(page).toHaveURL(/.*tournaments\.php/);
        
        // Apply rank range filter
        await page.selectOption('[name="rank_range"]', '1k-5k');
        
        // Verify filter applied without page reload
        await expect(page.locator('.tournament-card')).toBeVisible();
        
        // Search for Korean tournament
        await page.fill('[name="search"]', '한국');
        await page.press('[name="search"]', 'Enter');
        
        // Verify Korean search results
        const searchResults = await page.locator('.tournament-card');
        const firstResult = searchResults.first();
        await expect(firstResult).toContainText('한국');
        
        // Open tournament modal
        await firstResult.click();
        
        // Verify modal with Korean content
        const modal = page.locator('#tournament-modal');
        await expect(modal).toBeVisible();
        await expect(modal).toContainText('Registration');
        
        // Test external links open in new tabs
        const registrationLink = modal.locator('a[href*="forms.gle"]');
        await expect(registrationLink).toHaveAttribute('target', '_blank');
        
        // Close modal and verify scroll position preserved
        await page.press('Escape');
        await expect(modal).toBeHidden();
    });
});
```
