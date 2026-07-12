<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $translations = [
            'sostenibilita' => [
                'title' => 'Sustainability Report',
                'content' => '<h2>Sustainability</h2><p>Savino Del Bene Volley publishes its sustainability report annually, documenting the club\'s commitment in environmental (reducing event impact), social (inclusive projects), and governance (management transparency) areas.</p>',
                'meta_description' => 'The sustainability report of Savino Del Bene Volley. Environmental, social, and governance commitment.',
            ],
            'progetto-scuola' => [
                'title' => 'School Project',
                'content' => '<h2>School Project</h2><p>Savino Del Bene Volley brings volleyball and sports values to local schools. Through lessons, demonstrations, and interschool tournaments, we introduce young people to sports practice.</p>',
                'meta_description' => 'The school project of Savino Del Bene Volley. Promoting volleyball and sports values in schools.',
            ],
            'double-face' => [
                'title' => 'Double Face — The Magazine',
                'content' => '<h2>Double Face</h2><p>The official magazine of Savino Del Bene Volley. Exclusive interviews with athletes, tactical depth, behind the scenes, and stories from the youth sector. Available in digital format.</p>',
                'meta_description' => 'Double Face, the official magazine of Savino Del Bene Volley. Interviews, features, and behind the scenes.',
            ],
            'volley-4-all' => [
                'title' => 'Volley 4 All',
                'content' => '<h2>Volley 4 All</h2><p>A project that breaks down barriers and brings volleyball to everyone: people with disabilities, young people in situations of social hardship, and disadvantaged communities. Because sport is a right, not a privilege.</p>',
                'meta_description' => 'Volley 4 All: the social inclusion project of Savino Del Bene Volley. Sport for everyone, without barriers.',
            ],
            'progetti-sociali' => [
                'title' => 'Social Projects',
                'content' => '<p>Savino Del Bene Volley is actively committed to the local social fabric with initiatives of inclusion, education, and support for local communities.</p>',
                'meta_description' => 'The social projects of Savino Del Bene Volley. Inclusion, territory, and social responsibility.',
            ],
            'convenzioni' => [
                'title' => 'Agreements & Benefits',
                'content' => '<h2>Agreements</h2><p>Savino Del Bene Volley offers discounted rates for organized groups, schools, sports associations, and partner companies. Contact us to receive a personalized quote.</p>',
                'meta_description' => 'Agreements and benefits for groups, schools, and associations to attend Savino Del Bene Volley matches.',
            ],
            'accessibilita' => [
                'title' => 'Accessibility',
                'content' => '<h2>Accessibility</h2><p>Palazzo Wanny is equipped with reserved spaces for people with motor disabilities, easy access, dedicated restrooms, and staff trained for assistance. For information and reservations, please contact the secretariat.</p>',
                'meta_description' => 'Information on the accessibility of Palazzo Wanny for people with disabilities. Reserved spaces and dedicated services.',
            ],
            'title-sponsor' => [
                'title' => 'Title Sponsor',
                'content' => '<h2>Savino Del Bene S.p.A.</h2><p>The Savino Del Bene Group, a global leader in international logistics and shipping, has been the club\'s title sponsor since its foundation. A partnership that combines corporate excellence and sports passion.</p>',
                'meta_description' => 'Savino Del Bene S.p.A., title sponsor of Savino Del Bene Volley. Discover the partnership.',
            ],
            'diventa-sponsor' => [
                'title' => 'Become a Sponsor',
                'content' => '<h2>Why Sponsor Savino Del Bene Volley?</h2><p>National and international visibility, access to an exclusive B2B network, premium hospitality, and dedicated marketing activations. Contact us for a personalized quote.</p>',
                'meta_description' => 'Become a sponsor of Savino Del Bene Volley. Discover sponsorship packages and advantages for your business.',
            ],
            'hospitality' => [
                'title' => 'Hospitality',
                'content' => '<h2>Hospitality Experience</h2><p>Experience Savino Del Bene Volley matches from an exclusive perspective. Our Hospitality program offers premium seating, dedicated catering, meet & greet with the athletes, and networking with club partners.</p>',
                'meta_description' => 'Hospitality and premium services for Savino Del Bene Volley matches at Palazzo Wanny.',
            ],
            'affiliazioni' => [
                'title' => 'Affiliation Project',
                'content' => '<h2>Affiliation Program</h2><p>Savino Del Bene Volley offers an affiliation program for local sports clubs and volleyball schools. Technical training, sharing of methodologies, and participation in exclusive events.</p>',
                'meta_description' => 'Affiliation program of Savino Del Bene Volley for sports clubs and volleyball schools.',
            ],
        ];

        foreach ($translations as $slug => $data) {
            $page = Page::where('slug', $slug)->first();
            if ($page) {
                $page->setTranslation('title', 'en', $data['title']);
                $page->setTranslation('content', 'en', $data['content']);
                $page->setTranslation('meta_description', 'en', $data['meta_description']);
                $page->setTranslation('excerpt', 'en', $data['meta_description']);
                $page->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $slugs = [
            'sostenibilita', 'progetto-scuola', 'double-face', 'volley-4-all',
            'progetti-sociali', 'convenzioni', 'accessibilita', 'title-sponsor',
            'diventa-sponsor', 'hospitality', 'affiliazioni',
        ];

        foreach ($slugs as $slug) {
            $page = Page::where('slug', $slug)->first();
            if ($page) {
                $page->forgetTranslation('title', 'en');
                $page->forgetTranslation('content', 'en');
                $page->forgetTranslation('meta_description', 'en');
                $page->forgetTranslation('excerpt', 'en');
                $page->save();
            }
        }
    }
};
