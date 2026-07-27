<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BudgetPeriod;
use App\Enums\GoalStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Goal;
use App\Models\GoalDeposit;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Clean up existing test user
        User::where('email', 'test@example.com')->delete();

        // 2. Create target user (this triggers UserObserver to create 20 default categories)
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // 3. Load default categories from observer by name
        $categories = $user->categories()->get()->keyBy('name');

        // 4. Create custom categories
        $customCategoriesData = [
            'Gym & Fitness' => ['type' => TransactionType::Expense, 'color' => '#06b6d4', 'icon' => 'dumbbell'],
            'Tech & Gadgets' => ['type' => TransactionType::Expense, 'color' => '#a855f7', 'icon' => 'laptop'],
            'Coffee & Cafes' => ['type' => TransactionType::Expense, 'color' => '#b45309', 'icon' => 'coffee'],
            'Restaurants & Dining' => ['type' => TransactionType::Expense, 'color' => '#e11d48', 'icon' => 'utensils'],
            'Taxi & Rideshare' => ['type' => TransactionType::Expense, 'color' => '#f59e0b', 'icon' => 'car'],
            'Crypto & Web3' => ['type' => TransactionType::Expense, 'color' => '#8b5cf6', 'icon' => 'bitcoin'],
            'Software & SaaS' => ['type' => TransactionType::Expense, 'color' => '#3b82f6', 'icon' => 'code'],
            'Online Shopping' => ['type' => TransactionType::Expense, 'color' => '#ec4899', 'icon' => 'shopping-bag'],
            'Side Projects' => ['type' => TransactionType::Income,  'color' => '#10b981', 'icon' => 'rocket'],
            'Staking & Yield' => ['type' => TransactionType::Income,  'color' => '#8b5cf6', 'icon' => 'coins'],
            'YouTube & Content' => ['type' => TransactionType::Income,  'color' => '#ef4444', 'icon' => 'youtube'],
            'Cashback & Bonuses' => ['type' => TransactionType::Income,  'color' => '#22c55e', 'icon' => 'gift'],
        ];

        foreach ($customCategoriesData as $name => $meta) {
            $cat = $user->categories()->create([
                'name' => $name,
                'type' => $meta['type'],
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'is_default' => false,
            ]);
            $categories[$name] = $cat;
        }

        // 5. Create tags
        $tagImportant = $user->tags()->create(['name' => 'Important', 'color' => '#ef4444']);
        $tagLeisure = $user->tags()->create(['name' => 'Leisure',   'color' => '#3b82f6']);
        $tagWork = $user->tags()->create(['name' => 'Work',      'color' => '#10b981']);
        $tagHealth = $user->tags()->create(['name' => 'Health',    'color' => '#ec4899']);
        $tagPersonal = $user->tags()->create(['name' => 'Personal',  'color' => '#a855f7']);
        $tagCrypto = $user->tags()->create(['name' => 'Crypto',    'color' => '#f59e0b']);

        // 6. Create 4 financial accounts
        $card = $user->accounts()->create([
            'name' => 'Bank Card',
            'type' => AccountType::Card,
            'currency_code' => 'EUR',
            'balance' => 0,
            'color' => '#3b82f6',
            'icon' => 'credit-card',
        ]);

        $cash = $user->accounts()->create([
            'name' => 'Cash Wallet',
            'type' => AccountType::Cash,
            'currency_code' => 'EUR',
            'balance' => 0,
            'color' => '#10b981',
            'icon' => 'wallet',
        ]);

        $crypto = $user->accounts()->create([
            'name' => 'Crypto Wallet',
            'type' => AccountType::Crypto,
            'currency_code' => 'EUR',
            'balance' => 0,
            'color' => '#f59e0b',
            'icon' => 'bitcoin',
        ]);

        $paypal = $user->accounts()->create([
            'name' => 'PayPal Account',
            'type' => AccountType::PayPal,
            'currency_code' => 'EUR',
            'balance' => 0,
            'color' => '#2563eb',
            'icon' => 'paypal',
        ]);

        // Helper to record a transaction easily
        $addTx = function ($account, $category, $type, $amount, $date, $comment, $tags = [], $transferId = null, $relatedTxId = null) use ($user) {
            $tx = $user->transactions()->create([
                'account_id' => $account->id,
                'category_id' => $category ? $category->id : null,
                'type' => $type,
                'amount' => $amount,
                'currency_code' => 'EUR',
                'date' => $date,
                'comment' => $comment,
                'transfer_id' => $transferId,
                'related_transaction_id' => $relatedTxId,
            ]);

            if (! empty($tags)) {
                $tx->tags()->attach($tags);
            }

            return $tx;
        };

        // Helper to record a transfer between two accounts
        $addTransfer = function ($fromAccount, $toAccount, $amount, $date, $comment) use ($addTx) {
            $uuid = Str::uuid()->toString();
            $outTx = $addTx($fromAccount, null, TransactionType::Expense, $amount, $date, $comment, [], $uuid);
            $inTx = $addTx($toAccount, null, TransactionType::Income, $amount, $date, $comment, [], $uuid, $outTx->id);
            $outTx->update(['related_transaction_id' => $inTx->id]);

            return [$outTx, $inTx];
        };

        // SEEDING DECEMBER 2025 (~26 TRANSACTIONS)
        $decIncomes = [
            [5,  $card,   $categories['Salary'],             TransactionType::Income, 2450.00, 'Monthly Salary Part 1', [$tagWork]],
            [20, $card,   $categories['Salary'],             TransactionType::Income, 3600.00, 'Salary Part 2 & Year-End Bonus', [$tagWork]],
            [15, $paypal, $categories['Freelance'],          TransactionType::Income, 850.00,  'Freelance Landing Page Project', [$tagWork]],
            [22, $paypal, $categories['YouTube & Content'],  TransactionType::Income, 410.00,  'AdSense Payout Dec 2025', []],
        ];

        foreach ($decIncomes as [$day, $acc, $cat, $type, $amt, $comment, $tgs]) {
            $addTx($acc, $cat, $type, $amt, Carbon::create(2025, 12, $day), $comment, $tgs);
        }

        $decExpenses = [
            [1,  $card,   $categories['Housing & Rent'],     1150.00, 'Apartment Rent Dec 2025', [$tagImportant]],
            [1,  $card,   $categories['Gym & Fitness'],      49.00,   'Monthly Gym Membership', [$tagHealth]],
            [3,  $card,   $categories['Groceries'],          68.50,   'Lidl Supermarket Groceries', []],
            [6,  $card,   $categories['Utilities'],          145.00,  'Electricity & Heating bill', [$tagImportant]],
            [8,  $cash,   $categories['Coffee & Cafes'],     5.50,    'Espresso & croissant at café', [$tagLeisure]],
            [10, $card,   $categories['Subscriptions'],      14.99,   'Spotify Premium Family', [$tagLeisure]],
            [12, $card,   $categories['Groceries'],          92.00,   'Aldi Market groceries stock', []],
            [14, $paypal, $categories['Software & SaaS'],    20.00,   'ChatGPT Plus Subscription', [$tagWork]],
            [16, $card,   $categories['Gifts'],              185.00,  'New Year Presents for Family', [$tagPersonal]],
            [18, $card,   $categories['Restaurants & Dining'], 65.00, 'Italian Trattoria dinner', [$tagLeisure]],
            [19, $crypto, $categories['Crypto & Web3'],      12.50,   'NordVPN VPN subscription', [$tagCrypto]],
            [21, $cash,   $categories['Coffee & Cafes'],     6.20,    'Cappuccino & pastry', [$tagLeisure]],
            [23, $card,   $categories['Groceries'],          135.00,  'New Year Eve Party Supplies & Food', [$tagPersonal]],
            [24, $card,   $categories['Taxi & Rideshare'],   18.50,   'Uber ride home', []],
            [27, $paypal, $categories['Entertainment'],      34.99,   'Steam Winter Sale Game', [$tagLeisure]],
            [28, $cash,   $categories['Groceries'],          14.00,   'Fresh bakery sourdough & treats', []],
            [30, $card,   $categories['Bar'],                88.00,   'Pre-New Year drinks with friends', [$tagLeisure]],
            [31, $cash,   $categories['Taxi & Rideshare'],   26.00,   'New Year Night Taxi', []],
        ];

        foreach ($decExpenses as [$day, $acc, $cat, $amt, $comment, $tgs]) {
            $addTx($acc, $cat, TransactionType::Expense, $amt, Carbon::create(2025, 12, $day), $comment, $tgs);
        }

        $addTransfer($card, $cash, 450.00, Carbon::create(2025, 12, 3), 'ATM Cash Withdrawal');
        $addTransfer($card, $crypto, 200.00, Carbon::create(2025, 12, 10), 'Buy USDT on crypto exchange');

        // SEEDING 2026 (JANUARY 1 TO JULY 26, 2026 - 400+ TX)
        $months = [
            ['year' => 2026, 'month' => 1, 'maxDay' => 31],
            ['year' => 2026, 'month' => 2, 'maxDay' => 28],
            ['year' => 2026, 'month' => 3, 'maxDay' => 31],
            ['year' => 2026, 'month' => 4, 'maxDay' => 30],
            ['year' => 2026, 'month' => 5, 'maxDay' => 31],
            ['year' => 2026, 'month' => 6, 'maxDay' => 30],
            ['year' => 2026, 'month' => 7, 'maxDay' => 26],
        ];

        $groceryComments = [
            'Lidl supermarket weekly groceries',
            'Aldi market food & household supplies',
            'Carrefour Express quick food run',
            'Bio Organic Market fresh produce',
            'Supermarket fruits, dairy & bread',
            'Farmers market fresh berries & veggies',
            'Local supermarket pantry restock',
            'Grocery store healthy snacks',
        ];

        $coffeeComments = [
            'Morning espresso & croissant',
            'Cappuccino at specialty coffee shop',
            'Flat white with colleague',
            'Matcha latte & almond cake',
            'Iced latte afternoon break',
            'Batch brew filter coffee',
            'Espresso bar quick coffee',
            'Cold brew on warm afternoon',
        ];

        $restaurantComments = [
            'Italian Trattoria pizza & wine',
            'Sushi bar dinner with friends',
            'Ramen noodles lunch place',
            'Gourmet burger & craft beer',
            'Bistro evening dinner',
            'Asian fusion dining',
        ];

        $barComments = [
            'Friday drinks after work with team',
            'Craft beer taproom weekend',
            'Cocktail bar evening',
            'Pub quiz night & beers',
        ];

        $taxiComments = [
            'Uber ride to city center',
            'Bolt ride back in heavy rain',
            'Taxi to railway station',
            'Late night Uber trip home',
        ];

        $transportComments = [
            'Metro 10-ticket pass refill',
            'Subway day travel card',
            'Intercity express train ticket',
            'Regional bus transport ticket',
        ];

        $cashComments = [
            'Fresh sourdough bread at bakery',
            'Street food taco truck lunch',
            'Barber haircut & cash tip',
            'Flea market vintage vinyl record',
            'Local flower shop fresh bouquet',
            'Vendor fresh orange juice',
        ];

        $cryptoComments = [
            'VPS Cloud server node payment (SOL)',
            'Domain name registration renewal (USDT)',
            'Web3 RPC Node API provider tier',
            'DEX liquidity pool gas transaction fee',
            'IPFS decentralized storage pin',
            'Hardware wallet firmware backup key',
        ];

        $paypalComments = [
            'Steam digital game purchase',
            'Udemy course: Advanced Web Architecture',
            'eBay vintage tech accessory',
            'Fiverr custom vector icons pack',
            'Etsy handcrafted leather desk mat',
            'Digital ebook & programming guide',
        ];

        $healthComments = [
            'Pharmacy multivitamin & omega-3',
            'Skincare cleanser & SPF moisturizer',
            'Hygiene essentials & oral care',
            'Hair salon styling & shampoo',
        ];

        $techComments = [
            'Mechanical keyboard PBT keycaps set',
            'Wireless ergonomic vertical mouse',
            'Uniqlo casual summer clothing',
            'USB-C GaN 100W multi-port charger',
            'Desk monitor LED light bar',
            'Anker power bank fast charge',
        ];

        foreach ($months as $m) {
            $yr = $m['year'];
            $mo = $m['month'];
            $maxD = $m['maxDay'];

            // Helper to get safe Carbon date within month
            $d = fn ($day) => Carbon::create($yr, $mo, min($day, $maxD));

            //  INCOMES (~6-7 per month)
            $addTx($card, $categories['Salary'], TransactionType::Income, 2450.00 + ($mo * 20), $d(5), "Monthly Salary Part 1 ({$yr}-{$mo})", [$tagWork]);
            $addTx($card, $categories['Salary'], TransactionType::Income, 2450.00 + ($mo * 30), $d(20), "Monthly Salary Part 2 ({$yr}-{$mo})", [$tagWork]);

            if ($maxD >= 12) {
                $addTx($paypal, $categories['Freelance'], TransactionType::Income, rand(550, 950) + 0.50, $d(12), 'Freelance Web Development Project', [$tagWork]);
            }
            if ($maxD >= 25) {
                $addTx($card, $categories['Freelance'], TransactionType::Income, rand(400, 800) + 0.00, $d(25), 'Frontend Consulting Client Invoice', [$tagWork]);
            }
            if ($maxD >= 22) {
                $addTx($paypal, $categories['YouTube & Content'], TransactionType::Income, rand(280, 460) + 0.75, $d(22), "AdSense Monthly Payout {$mo}/{$yr}", []);
            }
            if ($maxD >= 15) {
                $addTx($crypto, $categories['Staking & Yield'], TransactionType::Income, rand(85, 160) + 0.20, $d(15), 'Crypto Staking & Yield Reward', [$tagCrypto]);
            }
            if ($maxD >= 28) {
                $addTx($card, $categories['Cashback & Bonuses'], TransactionType::Income, rand(25, 45) + 0.40, $d(28), 'Bank Monthly Credit Card Cashback', []);
            }

            //  FIXED SUBSCRIPTIONS & BILLS (~11 per month)
            $addTx($card, $categories['Housing & Rent'], TransactionType::Expense, 1150.00, $d(1), 'Apartment Monthly Rent', [$tagImportant]);
            $addTx($card, $categories['Gym & Fitness'], TransactionType::Expense, 49.00, $d(1), 'Gym & Fitness Membership', [$tagHealth]);

            if ($maxD >= 6) {
                $addTx($card, $categories['Utilities'], TransactionType::Expense, rand(135, 165) + 0.50, $d(6), 'Electricity & Heating Bill', [$tagImportant]);
            }
            if ($maxD >= 7) {
                $addTx($card, $categories['Utilities'], TransactionType::Expense, 39.99, $d(7), 'High-Speed Fiber Internet 1Gbps', []);
            }
            if ($maxD >= 8) {
                $addTx($card, $categories['Subscriptions'], TransactionType::Expense, 24.99, $d(8), 'Mobile Carrier Unlimited Plan', []);
            }
            if ($maxD >= 10) {
                $addTx($card, $categories['Subscriptions'], TransactionType::Expense, 14.99, $d(10), 'Spotify Family Premium', [$tagLeisure]);
            }
            if ($maxD >= 12) {
                $addTx($card, $categories['Subscriptions'], TransactionType::Expense, 17.99, $d(12), 'Netflix 4K Ultra HD Plan', [$tagLeisure]);
            }
            if ($maxD >= 14) {
                $addTx($paypal, $categories['Software & SaaS'], TransactionType::Expense, 20.00, $d(14), 'ChatGPT Plus AI Subscription', [$tagWork]);
            }
            if ($maxD >= 18) {
                $addTx($paypal, $categories['Software & SaaS'], TransactionType::Expense, 10.00, $d(18), 'GitHub Copilot Subscription', [$tagWork]);
            }
            if ($maxD >= 19) {
                $addTx($crypto, $categories['Crypto & Web3'], TransactionType::Expense, 12.50, $d(19), 'NordVPN Privacy Subscription', [$tagCrypto]);
            }
            if ($maxD >= 25) {
                $addTx($card, $categories['Subscriptions'], TransactionType::Expense, 9.99, $d(25), 'Apple iCloud 2TB Storage', []);
            }

            //  GROCERIES (10 per full month, 7 for July)
            $groceryDays = [2, 5, 8, 11, 14, 17, 20, 23, 26, 28];
            foreach ($groceryDays as $idx => $gDay) {
                if ($gDay <= $maxD) {
                    $acc = ($idx % 4 === 0) ? $cash : $card;
                    $cmt = $groceryComments[$idx % count($groceryComments)];
                    $amt = rand(28, 92) + rand(10, 99) / 100;
                    $addTx($acc, $categories['Groceries'], TransactionType::Expense, $amt, $d($gDay), $cmt, []);
                }
            }

            //  COFFEE & CAFES (12 per full month, 9 for July)
            $coffeeDays = [1, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 24, 27];
            foreach ($coffeeDays as $idx => $cDay) {
                if ($cDay <= $maxD) {
                    $acc = ($idx % 3 === 0) ? $card : $cash;
                    $cmt = $coffeeComments[$idx % count($coffeeComments)];
                    $amt = rand(3, 11) + rand(20, 90) / 100;
                    $addTx($acc, $categories['Coffee & Cafes'], TransactionType::Expense, $amt, $d($cDay), $cmt, [$tagLeisure]);
                }
            }

            //  RESTAURANTS & DINING (5 per full month, 4 for July)
            $restDays = [4, 11, 18, 24, 27];
            foreach ($restDays as $idx => $rDay) {
                if ($rDay <= $maxD) {
                    $cmt = $restaurantComments[$idx % count($restaurantComments)];
                    $amt = rand(26, 76) + rand(0, 99) / 100;
                    $addTx($card, $categories['Restaurants & Dining'], TransactionType::Expense, $amt, $d($rDay), $cmt, [$tagLeisure]);
                }
            }

            //  BAR & NIGHTLIFE (3 per full month, 2 for July)
            $barDays = [6, 13, 26];
            foreach ($barDays as $idx => $bDay) {
                if ($bDay <= $maxD) {
                    $acc = ($idx % 2 === 0) ? $card : $cash;
                    $cmt = $barComments[$idx % count($barComments)];
                    $amt = rand(18, 58) + rand(0, 90) / 100;
                    $addTx($acc, $categories['Bar'], TransactionType::Expense, $amt, $d($bDay), $cmt, [$tagLeisure]);
                }
            }

            //  TAXI & RIDESHARE (4 per full month, 3 for July)
            $taxiDays = [3, 10, 19, 25];
            foreach ($taxiDays as $idx => $tDay) {
                if ($tDay <= $maxD) {
                    $acc = ($idx === 3) ? $cash : $card;
                    $cmt = $taxiComments[$idx % count($taxiComments)];
                    $amt = rand(8, 24) + rand(10, 90) / 100;
                    $addTx($acc, $categories['Taxi & Rideshare'], TransactionType::Expense, $amt, $d($tDay), $cmt, []);
                }
            }

            //  TRANSPORT (4 per full month, 3 for July)
            $transDays = [2, 9, 16, 23];
            foreach ($transDays as $idx => $trDay) {
                if ($trDay <= $maxD) {
                    $cmt = $transportComments[$idx % count($transportComments)];
                    $amt = ($idx % 2 === 0) ? rand(2, 6) + 0.50 : rand(22, 38) + 0.00;
                    $addTx($card, $categories['Transport'], TransactionType::Expense, $amt, $d($trDay), $cmt, []);
                }
            }

            //  CASH WALLET EXPENSES (4 per full month, 3 for July)
            $cashDays = [4, 12, 19, 26];
            foreach ($cashDays as $idx => $csDay) {
                if ($csDay <= $maxD) {
                    $cmt = $cashComments[$idx % count($cashComments)];
                    $amt = rand(5, 38) + rand(10, 90) / 100;
                    $addTx($cash, $categories['Groceries'], TransactionType::Expense, $amt, $d($csDay), $cmt, []);
                }
            }

            //  CRYPTO WALLET ONLINE EXPENSES (3 per full month, 2 for July)
            $cryptoDays = [7, 16, 24];
            foreach ($cryptoDays as $idx => $crDay) {
                if ($crDay <= $maxD) {
                    $cmt = $cryptoComments[$idx % count($cryptoComments)];
                    $amt = rand(14, 95) + rand(10, 99) / 100;
                    $addTx($crypto, $categories['Crypto & Web3'], TransactionType::Expense, $amt, $d($crDay), $cmt, [$tagCrypto]);
                }
            }

            //  PAYPAL ONLINE PURCHASES (4 per full month, 3 for July)
            $paypalDays = [9, 15, 21, 27];
            foreach ($paypalDays as $idx => $ppDay) {
                if ($ppDay <= $maxD) {
                    $cmt = $paypalComments[$idx % count($paypalComments)];
                    $amt = rand(12, 85) + rand(0, 99) / 100;
                    $addTx($paypal, $categories['Online Shopping'], TransactionType::Expense, $amt, $d($ppDay), $cmt, []);
                }
            }

            //  HEALTHCARE & PERSONAL CARE (2 per full month)
            $healthDays = [11, 22];
            foreach ($healthDays as $idx => $hDay) {
                if ($hDay <= $maxD) {
                    $cmt = $healthComments[$idx % count($healthComments)];
                    $amt = rand(16, 68) + rand(0, 90) / 100;
                    $addTx($card, $categories['Healthcare'], TransactionType::Expense, $amt, $d($hDay), $cmt, [$tagHealth]);
                }
            }

            //  TECH & GADGETS / SHOPPING (2 per full month)
            $techDays = [14, 25];
            foreach ($techDays as $idx => $tcDay) {
                if ($tcDay <= $maxD) {
                    $acc = ($idx % 2 === 0) ? $card : $paypal;
                    $cmt = $techComments[$idx % count($techComments)];
                    $amt = rand(42, 290) + rand(0, 99) / 100;
                    $addTx($acc, $categories['Tech & Gadgets'], TransactionType::Expense, $amt, $d($tcDay), $cmt, [$tagImportant]);
                }
            }

            //  TRANSFERS (3 per month: Card -> Cash, Card -> Crypto, PayPal -> Card)
            if ($maxD >= 3) {
                $addTransfer($card, $cash, 450.00, $d(3), 'ATM Cash Withdrawal');
            }
            if ($maxD >= 10) {
                $addTransfer($card, $crypto, 200.00, $d(10), 'Buy USDT on crypto exchange');
            }
            if ($maxD >= 25) {
                $addTransfer($paypal, $card, 400.00, $d(25), 'Transfer PayPal balance to Bank Card');
            }
        }

        // SEED BUDGETS
        $budgetsData = [
            ['category' => $categories['Groceries'],            'amount' => 600.00],
            ['category' => $categories['Restaurants & Dining'], 'amount' => 300.00],
            ['category' => $categories['Bar'],                  'amount' => 150.00],
            ['category' => $categories['Subscriptions'],        'amount' => 100.00],
            ['category' => $categories['Taxi & Rideshare'],    'amount' => 120.00],
            ['category' => $categories['Online Shopping'],      'amount' => 350.00],
            ['category' => $categories['Travel'],               'amount' => 1000.00],
        ];

        foreach ($budgetsData as $b) {
            $user->budgets()->create([
                'category_id' => $b['category']->id,
                'period' => BudgetPeriod::Monthly,
                'amount' => $b['amount'],
                'currency_code' => 'EUR',
            ]);
        }

        // SEED GOALS & GOAL DEPOSITS
        $macbookGoal = $user->goals()->create([
            'name' => 'New MacBook Pro M3',
            'target_amount' => 2500.00,
            'current_amount' => 1800.00,
            'currency_code' => 'EUR',
            'status' => GoalStatus::Active,
            'deadline' => Carbon::now()->addMonths(4),
        ]);

        foreach ([
            [Carbon::create(2026, 2, 15), 600.00, 'Saving deposit 1 for MacBook'],
            [Carbon::create(2026, 4, 15), 600.00, 'Saving deposit 2 for MacBook'],
            [Carbon::create(2026, 6, 15), 600.00, 'Saving deposit 3 for MacBook'],
        ] as [$date, $amt, $cmt]) {
            $depTx = $addTx($card, null, TransactionType::Expense, $amt, $date, $cmt, [$tagImportant]);
            GoalDeposit::create([
                'goal_id' => $macbookGoal->id,
                'transaction_id' => $depTx->id,
                'amount' => $amt,
                'comment' => $cmt,
            ]);
        }

        $japanGoal = $user->goals()->create([
            'name' => 'Summer Trip to Japan',
            'target_amount' => 4000.00,
            'current_amount' => 2500.00,
            'currency_code' => 'EUR',
            'status' => GoalStatus::Active,
            'deadline' => Carbon::now()->addMonths(6),
        ]);

        foreach ([
            [Carbon::create(2026, 1, 28), 500.00, 'Japan trip deposit Jan'],
            [Carbon::create(2026, 3, 28), 500.00, 'Japan trip deposit Mar'],
            [Carbon::create(2026, 5, 28), 500.00, 'Japan trip deposit May'],
            [Carbon::create(2026, 6, 28), 500.00, 'Japan trip deposit Jun'],
            [Carbon::create(2026, 7, 20), 500.00, 'Japan trip deposit Jul'],
        ] as [$date, $amt, $cmt]) {
            $depTx = $addTx($card, null, TransactionType::Expense, $amt, $date, $cmt, [$tagLeisure]);
            GoalDeposit::create([
                'goal_id' => $japanGoal->id,
                'transaction_id' => $depTx->id,
                'amount' => $amt,
                'comment' => $cmt,
            ]);
        }

        // CALCULATE AND UPDATE ACCOUNT BALANCES
        foreach ($user->accounts as $account) {
            $incomes = Transaction::where('account_id', $account->id)
                ->where('type', TransactionType::Income)
                ->sum('amount');

            $expenses = Transaction::where('account_id', $account->id)
                ->where('type', TransactionType::Expense)
                ->sum('amount');

            $account->update(['balance' => $incomes - $expenses]);
        }
    }
}
