<?php

declare(strict_types=1);


namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * Acceptance tests for the home page.
 */
final class HomeCest
{
	/**
	 * Shows a list of six recent games.
	 */
	public function recentGamesList(AcceptanceTester $I): void
	{
		$I->amOnPage('/');
		$I->seeNumberOfElements('[data-testid="recent-games"] a', 6);
	}

	/**
	 * Shows a list of recently added games with pagination links.
	 */
	public function recentlyAddedList(AcceptanceTester $I): void
	{
		$I->amOnPage('/');
		$I->see('Recently Added', 'h2');
		$I->seeNumberOfElements('[data-testid="recently-added"] .row', 18);
		$I->seeLink('Next >>', '/recently_added.php?page=2');
	}
}
