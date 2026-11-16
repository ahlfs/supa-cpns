# CPNS Tryout Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a complete, free CPNS SKD tryout to the existing `supa-cpns` Laravel app: accounts, a question bank with an admin panel, a server-authoritative timed exam room, self-scoring at submit, and a score-analysis page.

**Architecture:** One Laravel 13 app, one SQLite database, Blade views plus data-attribute JS modules (the pattern the marketing site already uses). The exam engine is server-authoritative: an `ExamAttempt` row owns the clock (`started_at` + `expires_at`), every answer is one upserted row keyed on (attempt, question), and grading happens exactly once inside one service that freezes the result onto the answer rows. Read pages only read stored numbers.

**Tech Stack:** PHP 8.3, Laravel 13.42, Blade, Tailwind v4 (`source(none)` + three `@source` lines), Vite 8, PHPUnit 12, SQLite. No new Composer or npm dependency is added anywhere in this plan.

**Spec:** `docs/SPEC-CPNS-TRYOUT.md`

**Plan location note:** saved under `docs/plans/` rather than the skill's default `docs/superpowers/plans/` so it sits beside the spec it implements in this repo's flat `docs/` directory.

## Global Constraints

Copied from the spec; every task's requirements implicitly include this section.

- Scoring: TWK 30 items × 5 (max 150, threshold 65); TIU 35 items × 5 (max 175, threshold 80); TKP 45 items with option weight 1–5 (max 225, threshold 166); total 110 items, max 550, duration 100 minutes. No negative marking anywhere. Thresholds are **independent**, never summed.
- Every threshold, maximum, item count and duration lives in `config/cpns.php`. No number from the list above may appear hard-coded in a view or controller.
- Seven new tables, three new columns on `users`. `sessions`, `cache`, `jobs` and `password_reset_tokens` are left exactly as they are (`password_reset_tokens` stays unused — email is dead on this host).
- All question content is authored in-house against the official blueprint. No scraped or reworded third-party item enters the bank (spec §3).
- Every new Blade file lives under `resources/views`, because Tailwind runs with `source(none)` and only scans `views`, `js` and `content`.
- Tailwind utilities only — no new CSS file, no BEM, no `<style>` block. New tokens are added only if a need appears; the existing set already covers every state this module has.
- Layouts vary one property at a time with `lg:` (992px) plus optionally `max-sm:`; never `md:` and `lg:` on the same property.
- Icons are real SVG files in `resources/icons/` rendered through `<x-icon name="...">`. Never an emoji, never an icon font, never a remote URL.
- Model style follows the code already in the repo: `#[Fillable([...])]` / `#[Hidden([...])]` attributes and a `casts()` method. No array-style `$fillable`.
- Timer arithmetic uses `getTimestamp()` subtraction, never `Carbon::diffInSeconds()`: Carbon 3's sign convention is not worth reasoning about in a timed exam.
- Every test class is a `final class ... extends Tests\TestCase` with `declare(strict_types=1)`, matching the existing `tests/Feature/HomepageTest.php`.
- Git: commit locally after each task. Never push.

---

### Task 1: Domain foundation — config, enums, user role columns, gate

**Files:**
- Create: `config/cpns.php`
- Create: `app/Enums/UserRole.php`, `app/Enums/QuestionCategory.php`, `app/Enums/AttemptStatus.php`
- Create: `database/migrations/2026_11_15_000001_add_role_columns_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Domain/UserRoleTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Enums\UserRole{Peserta,Admin}`, `App\Enums\QuestionCategory{Twk,Tiu,Tkp}` with `label()`, `requiresKey()`, `subcategories()`, `config(string $key)`, `itemCount()`, `maxScore()`, `threshold()`; `App\Enums\AttemptStatus{InProgress,Submitted,Expired}` with `isOpen()`; `User::isAdmin()`, `User::attempts()`, `User::assignParticipantNumber()`, `UserFactory::admin()`; `config('cpns.*')`.

- [ ] **Step 1: Write `config/cpns.php`**

```php
<?php

declare(strict_types=1);

/*
 * The CPNS SKD blueprint. Every number the exam engine, the grader, the results
 * page and the admin panel needs lives here, so no view or controller carries a
 * copy of "110" or "166" that can drift from the regulation.
 *
 * Source: KepmenPANRB 321/2024 as published, cross-checked against the BKN
 * selection handbook and the cat.bkn.go.id simulation (see docs/SPEC-CPNS-TRYOUT.md §4).
 */
return [
    /* Per-subtest rules. `correct`/`wrong` are null for TKP because its score
       comes from the chosen option's weight, not from being right. */
    'subtests' => [
        'twk' => [
            'label' => 'TWK',
            'name' => 'Tes Wawasan Kebangsaan',
            'items' => 30,
            'correct' => 5,
            'wrong' => 0,
            'max' => 150,
            'threshold' => 65,
        ],
        'tiu' => [
            'label' => 'TIU',
            'name' => 'Tes Intelegensia Umum',
            'items' => 35,
            'correct' => 5,
            'wrong' => 0,
            'max' => 175,
            'threshold' => 80,
        ],
        'tkp' => [
            'label' => 'TKP',
            'name' => 'Tes Karakteristik Pribadi',
            'items' => 45,
            'correct' => null,
            'wrong' => 0,
            'max' => 225,
            'threshold' => 166,
        ],
    ],

    'total_items' => 110,
    'total_max' => 550,
    'duration_minutes' => 100,
    /* The netra arrangement. Stored for reference; not selectable in the UI yet. */
    'duration_minutes_netra' => 130,

    /* Special-formation cumulative thresholds. Stored, deliberately NOT enforced:
       participants do not pick a formation (spec §4). */
    'special_thresholds' => [
        'cumlaude' => ['total' => 311, 'tiu' => 85],
        'diaspora' => ['total' => 311, 'tiu' => 85],
        'disabilitas' => ['total' => 286, 'tiu' => 60],
        'papua' => ['total' => 286, 'tiu' => 60],
    ],

    /* The kisi-kisi subtopics offered in the question form and the importer. */
    'subcategories' => [
        'twk' => [
            'nasionalisme' => 'Nasionalisme',
            'integritas' => 'Integritas',
            'bela_negara' => 'Bela Negara',
            'pilar_negara' => 'Pilar Negara',
            'bahasa_indonesia' => 'Bahasa Indonesia',
        ],
        'tiu' => [
            'analogi_verbal' => 'Analogi Verbal',
            'silogisme' => 'Silogisme',
            'analitis' => 'Analitis',
            'deret_angka' => 'Deret Angka',
            'aritmetika' => 'Aritmetika',
            'perbandingan_kuantitatif' => 'Perbandingan Kuantitatif',
            'soal_cerita' => 'Soal Cerita',
            'analogi_figural' => 'Analogi Figural',
            'ketidaksamaan_figural' => 'Ketidaksamaan Figural',
            'serial_figural' => 'Serial Figural',
        ],
        'tkp' => [
            'pelayanan_publik' => 'Pelayanan Publik',
            'jejaring_kerja' => 'Jejaring Kerja',
            'sosial_budaya' => 'Sosial Budaya',
            'teknologi_informasi' => 'Teknologi Informasi dan Komunikasi',
            'profesionalisme' => 'Profesionalisme',
            'anti_radikalisme' => 'Anti Radikalisme',
        ],
    ],

    /* Per-item timing is an estimate. One interval can never contribute more than
       this, so an abandoned tab cannot absorb half an hour (spec §6.4). */
    'timing_cap_seconds' => 300,
    /* Answered this fast and wrong is near-certainly a guess. */
    'guess_threshold_seconds' => 10,

    'password' => [
        'min_length' => 8,
        'login_attempts_per_minute' => 5,
    ],
];
```

- [ ] **Step 2: Write the three enums**

`app/Enums/UserRole.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Peserta = 'peserta';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Peserta => 'Peserta',
            self::Admin => 'Administrator',
        };
    }
}
```

`app/Enums/QuestionCategory.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three SKD subtests. TWK and TIU are graded against a key; TKP is graded by
 * the weight on the chosen option, because in TKP every option is defensible and
 * only its fit with the ASN values separates them.
 */
enum QuestionCategory: string
{
    case Twk = 'twk';
    case Tiu = 'tiu';
    case Tkp = 'tkp';

    public function label(): string
    {
        return (string) $this->config('label');
    }

    public function name(): string
    {
        return (string) $this->config('name');
    }

    /** True when the item needs a single correct option. TKP never does. */
    public function requiresKey(): bool
    {
        return $this !== self::Tkp;
    }

    public function itemCount(): int
    {
        return (int) $this->config('items');
    }

    public function maxScore(): int
    {
        return (int) $this->config('max');
    }

    public function scorePerCorrect(): int
    {
        return (int) $this->config('correct');
    }

    public function threshold(): int
    {
        return (int) $this->config('threshold');
    }

    /** @return array<string, string> subtopic key => human label */
    public function subcategories(): array
    {
        return (array) config('cpns.subcategories.'.$this->value, []);
    }

    public function subcategoryLabel(string $key): string
    {
        return $this->subcategories()[$key] ?? $key;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return config('cpns.subtests.'.$this->value.'.'.$key, $default);
    }
}
```

`app/Enums/AttemptStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Expired = 'expired';

    /** An attempt that can still accept answers. */
    public function isOpen(): bool
    {
        return $this === self::InProgress;
    }

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang dikerjakan',
            self::Submitted => 'Selesai',
            self::Expired => 'Waktu habis',
        };
    }
}
```

- [ ] **Step 3: Write the migration for the three user columns**

`database/migrations/2026_11_15_000001_add_role_columns_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('peserta');
            $table->string('participant_number')->nullable()->unique();
            $table->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['participant_number']);
            $table->dropColumn(['role', 'participant_number', 'must_change_password']);
        });
    }
};
```

- [ ] **Step 4: Extend the `User` model in the repo's attribute style**

Replace the body of `app/Models/User.php` with:

```php
<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'participant_number', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'bool',
        ];
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Participant numbers are derived from the row id, so they are unique without
     * a second sequence to keep in step. Assigned once, right after registration.
     */
    public function assignParticipantNumber(): void
    {
        $this->forceFill([
            'participant_number' => sprintf(
                '%s-%06d',
                $this->created_at?->format('Y') ?? now()->format('Y'),
                $this->getKey(),
            ),
        ])->save();
    }
}
```

- [ ] **Step 5: Add the admin factory state**

Append to `database/factories/UserFactory.php`, inside the class, and add the import `use App\Enums\UserRole;`:

```php
    /**
     * An account that passes every gate.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin->value,
        ]);
    }
```

- [ ] **Step 6: Register `Gate::before` so admins pass every check**

In `app/Providers/AppServiceProvider.php`, add the imports `use App\Models\User;` and `use Illuminate\Support\Facades\Gate;`, then replace the empty `boot()`:

```php
    public function boot(): void
    {
        // One line instead of a policy per model: an admin passes everything, and
        // a null return hands the decision to whatever check runs next.
        Gate::before(static fn (User $user): ?bool => $user->isAdmin() ? true : null);
    }
```

- [ ] **Step 7: Write the failing test**

`tests/Feature/Domain/UserRoleTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_casts_to_an_enum_and_defaults_to_peserta(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Peserta, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_the_admin_state_passes_every_gate_and_a_peserta_passes_none(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('anything-at-all'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('anything-at-all'));
    }

    public function test_a_participant_number_is_derived_from_the_year_and_the_id(): void
    {
        $user = User::factory()->create();
        $user->assignParticipantNumber();

        $this->assertSame(
            sprintf('%s-%06d', now()->format('Y'), $user->id),
            $user->fresh()->participant_number,
        );
    }

    public function test_the_blueprint_numbers_come_from_config_and_not_from_code(): void
    {
        $this->assertSame(30, QuestionCategory::Twk->itemCount());
        $this->assertSame(35, QuestionCategory::Tiu->itemCount());
        $this->assertSame(45, QuestionCategory::Tkp->itemCount());
        $this->assertSame(150, QuestionCategory::Twk->maxScore());
        $this->assertSame(175, QuestionCategory::Tiu->maxScore());
        $this->assertSame(225, QuestionCategory::Tkp->maxScore());
        $this->assertSame(65, QuestionCategory::Twk->threshold());
        $this->assertSame(80, QuestionCategory::Tiu->threshold());
        $this->assertSame(166, QuestionCategory::Tkp->threshold());
        $this->assertSame(110, config('cpns.total_items'));
        $this->assertSame(550, config('cpns.total_max'));
        $this->assertSame(100, config('cpns.duration_minutes'));

        $this->assertTrue(QuestionCategory::Twk->requiresKey());
        $this->assertTrue(QuestionCategory::Tiu->requiresKey());
        $this->assertFalse(QuestionCategory::Tkp->requiresKey());

        $this->assertTrue(AttemptStatus::InProgress->isOpen());
        $this->assertFalse(AttemptStatus::Submitted->isOpen());
        $this->assertFalse(AttemptStatus::Expired->isOpen());
    }
}
```

- [ ] **Step 8: Run the test to verify it fails**

Run: `php artisan test --filter=UserRoleTest`
Expected: FAIL — `Call to undefined method App\Models\User::isAdmin()` (and, once that exists, the enum assertions fail because the enums do not exist yet).

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --filter=UserRoleTest`
Expected: PASS, 4 tests.

- [ ] **Step 10: Commit**

```bash
git add config/cpns.php app/Enums app/Models/User.php database/factories/UserFactory.php database/migrations/2026_11_15_000001_add_role_columns_to_users_table.php app/Providers/AppServiceProvider.php tests/Feature/Domain/UserRoleTest.php
git commit -m "feat(exam): blueprint config, domain enums and the account role column"
```

---

### Task 2: Question bank — bank tables, models, factory

**Files:**
- Create: `database/migrations/2026_11_15_000002_create_questions_table.php`
- Create: `database/migrations/2026_11_15_000003_create_question_options_table.php`
- Create: `app/Models/Question.php`, `app/Models/QuestionOption.php`
- Create: `database/factories/QuestionFactory.php`
- Test: `tests/Feature/Domain/QuestionBankTest.php`

**Interfaces:**
- Consumes: `App\Enums\QuestionCategory`, `config('cpns.subcategories')`.
- Produces: `Question::options()`, `Question::packages()`, `Question::correctOption()`, `Question::subcategoryLabel()`, `Question::scopeActive()`, `Question::scopeCategory()`; `QuestionOption::question()`; `QuestionFactory::category(QuestionCategory $category)` plus `twk()`/`tiu()`/`tkp()` states, each creating the item's five A–E options on create.

- [ ] **Step 1: Write the two bank migrations**

`database/migrations/2026_11_15_000002_create_questions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('subcategory');
            $table->text('stem');
            $table->text('explanation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('subcategory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
```

`database/migrations/2026_11_15_000003_create_question_options_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 1);
            $table->text('body');
            // Exactly one of these two is set per option: `is_correct` for TWK/TIU,
            // `score` (1-5) for TKP. Which one applies is enforced in the admin
            // FormRequest, not here, because the rule differs per category.
            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
```

- [ ] **Step 2: Write the two models**

`app/Models/Question.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionCategory;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One bank item. It belongs to the bank, not to a package, so one item serves
 * several tryouts and the admin never retypes it — which is also why freeing
 * package composition costs almost nothing.
 */
#[Fillable(['category', 'subcategory', 'stem', 'explanation', 'is_active'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => QuestionCategory::class,
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('label');
    }

    /**
     * @return BelongsToMany<ExamPackage, $this>
     */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ExamPackage::class, 'exam_package_question')
            ->withPivot('position');
    }

    public function correctOption(): ?QuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function subcategoryLabel(): string
    {
        return $this->category->subcategoryLabel($this->subcategory);
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeCategory(Builder $query, QuestionCategory $category): void
    {
        $query->where('category', $category->value);
    }
}
```

`app/Models/QuestionOption.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'label', 'body', 'is_correct', 'score'])]
class QuestionOption extends Model
{
    /** @use HasFactory<QuestionOptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'bool',
            'score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
```

- [ ] **Step 3: Write the question factory, options included**

`database/factories/QuestionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionCategory;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => QuestionCategory::Twk->value,
            'subcategory' => 'pilar_negara',
            'stem' => 'Soal '.fake()->unique()->numberBetween(1, 9_999_999),
            'explanation' => 'Pembahasan: '.fake()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Every item gets its five options at the moment it is created: A-E is not a
     * convention here, it is the shape of the exam, and a test or a seeder that
     * forgot them would produce an item nobody can answer.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            $needsKey = $question->category->requiresKey();

            foreach (range('A', 'E') as $index => $label) {
                $question->options()->create([
                    'label' => $label,
                    'body' => "Pilihan {$label} untuk soal #{$question->id}",
                    'is_correct' => $needsKey ? $label === 'A' : null,
                    'score' => $needsKey ? null : $index + 1,
                ]);
            }
        });
    }

    public function category(QuestionCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category->value,
            'subcategory' => (string) array_key_first($category->subcategories()),
        ]);
    }

    public function twk(): static
    {
        return $this->category(QuestionCategory::Twk);
    }

    public function tiu(): static
    {
        return $this->category(QuestionCategory::Tiu);
    }

    public function tkp(): static
    {
        return $this->category(QuestionCategory::Tkp);
    }
}
```

- [ ] **Step 4: Write the failing test**

`tests/Feature/Domain/QuestionBankTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_item_is_created_with_its_five_labelled_options(): void
    {
        $question = Question::factory()->twk()->create();

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $question->options->pluck('label')->all());
        $this->assertSame(1, $question->options->where('is_correct', true)->count());
        $this->assertSame(QuestionCategory::Twk, $question->category);
        $this->assertSame('nasionalisme', $question->subcategory);
    }

    public function test_a_tkp_item_carries_a_full_weight_ladder_and_no_key(): void
    {
        $question = Question::factory()->tkp()->create();

        $this->assertSame([1, 2, 3, 4, 5], $question->options->pluck('score')->sort()->values()->all());
        $this->assertSame(0, $question->options->whereNotNull('is_correct')->count());
        $this->assertNull($question->correctOption());
    }

    public function test_subcategory_labels_come_from_the_blueprint(): void
    {
        $question = Question::factory()->tiu()->create();

        $this->assertSame('Analogi Verbal', $question->subcategoryLabel());
    }
}
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `php artisan test --filter=QuestionBankTest`
Expected: FAIL — `Class "App\Models\Question" not found`.

- [ ] **Step 6: Create `QuestionOptionFactory`** (referred by the model's `HasFactory` generic)

`database/factories/QuestionOptionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label' => 'A',
            'body' => fake()->sentence(),
            'is_correct' => true,
            'score' => null,
        ];
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=QuestionBankTest`
Expected: PASS, 3 tests.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_11_15_000002_create_questions_table.php database/migrations/2026_11_15_000003_create_question_options_table.php app/Models/Question.php app/Models/QuestionOption.php database/factories/QuestionFactory.php database/factories/QuestionOptionFactory.php tests/Feature/Domain/QuestionBankTest.php
git commit -m "feat(exam): question bank tables, models and factory"
```

---

### Task 3: Packages, the pivot, and the composition rules

**Files:**
- Create: `database/migrations/2026_11_15_000004_create_exam_packages_table.php`
- Create: `database/migrations/2026_11_15_000005_create_exam_package_question_table.php`
- Create: `app/Models/ExamPackage.php`
- Create: `database/factories/ExamPackageFactory.php`
- Create: `app/Support/PackageComposition.php`
- Test: `tests/Feature/Domain/PackageCompositionTest.php`

**Interfaces:**
- Consumes: `Question`, `QuestionCategory`, `config('cpns.total_items')`.
- Produces: `ExamPackage::STATUS_DRAFT`, `ExamPackage::STATUS_PUBLISHED`, `ExamPackage::isPublished()`, `ExamPackage::questions()`, `ExamPackage::attempts()`, `ExamPackage::isLocked()`, `ExamPackage::addQuestion(Question $question): void`, `ExamPackage::nextPosition(): int`; `PackageComposition::report(ExamPackage $package): array`, `PackageComposition::isComplete(ExamPackage $package): bool`, `PackageComposition::shortfallMessage(ExamPackage $package): string`; `ExamPackageFactory::draft()`, `::published()`, `::complete()`.

- [ ] **Step 1: Write the two migrations**

`database/migrations/2026_11_15_000004_create_exam_packages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(100);
            $table->string('status')->default('draft');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_packages');
    }
};
```

`database/migrations/2026_11_15_000005_create_exam_package_question_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_package_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            // No duplicate item and no gap in numbering.
            $table->unique(['exam_package_id', 'question_id']);
            $table->unique(['exam_package_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_package_question');
    }
};
```

The unique key on `(exam_package_id, position)` means a reorder cannot write the new positions one by one — a swap collides with the row that still holds the target position. The builder handles this by bumping the whole package out of range first (Task 15, Step 4).

- [ ] **Step 2: Write the `ExamPackage` model**

`app/Models/ExamPackage.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'slug', 'description', 'duration_minutes', 'status', 'opens_at', 'closes_at'])]
class ExamPackage extends Model
{
    /** @use HasFactory<ExamPackageFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'int',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_package_question')
            ->withPivot('position')
            ->orderBy('exam_package_question.position');
    }

    /**
     * @return HasMany<ExamAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Once anyone has sat the package its composition is frozen, because two
     * sittings of different questions are not comparable on the history chart.
     * The items themselves stay editable everywhere (spec decision 13).
     */
    public function isLocked(): bool
    {
        return $this->attempts()->exists();
    }

    public function nextPosition(): int
    {
        return (int) $this->questions()->max('exam_package_question.position') + 1;
    }

    public function addQuestion(Question $question): void
    {
        $this->questions()->attach($question->id, ['position' => $this->nextPosition()]);
    }

    /**
     * The route key is the slug: package URLs read better than ids.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, mixed> */
    public function toContentArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
        ];
    }
}
```

- [ ] **Step 3: Write `PackageComposition`, the one place 30/35/45 is implemented**

`app/Support/PackageComposition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;

/**
 * Whether a package is a real SKD exam. Publishing, the builder's shortfall bar
 * and the admin dashboard all ask this class, so the rule "30 TWK, 35 TIU, 45 TKP"
 * exists once and cannot drift between the three places that enforce it.
 */
final class PackageComposition
{
    /**
     * @return array<string, array{label: string, required: int, present: int, missing: int, complete: bool}>
     */
    public static function report(ExamPackage $package): array
    {
        $present = $package->questions()
            ->selectRaw('questions.category as category, count(*) as total')
            ->groupBy('questions.category')
            ->pluck('total', 'category');

        $report = [];

        foreach (QuestionCategory::cases() as $category) {
            $required = $category->itemCount();
            $have = (int) ($present[$category->value] ?? 0);

            $report[$category->value] = [
                'label' => $category->label(),
                'required' => $required,
                'present' => $have,
                'missing' => max(0, $required - $have),
                'complete' => $have === $required,
            ];
        }

        return $report;
    }

    public static function isComplete(ExamPackage $package): bool
    {
        foreach (self::report($package) as $row) {
            if (! $row['complete']) {
                return false;
            }
        }

        return true;
    }

    /**
     * "TWK kurang 4, TKP kurang 12." Empty string when the package is complete.
     */
    public static function shortfallMessage(ExamPackage $package): string
    {
        $missing = [];

        foreach (self::report($package) as $row) {
            if (! $row['complete']) {
                $missing[] = $row['label'].' kurang '.$row['missing'];
            }
        }

        return $missing === [] ? '' : implode(', ', $missing).'.';
    }
}
```

- [ ] **Step 4: Write the package factory, including a complete 110-item state**

`database/factories/ExamPackageFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamPackage>
 */
class ExamPackageFactory extends Factory
{
    protected $model = ExamPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Tryout SKD '.fake()->unique()->numberBetween(1, 999_999);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => 'Latihan SKD lengkap dengan analisa skor.',
            'duration_minutes' => (int) config('cpns.duration_minutes'),
            'status' => ExamPackage::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ExamPackage::STATUS_PUBLISHED]);
    }

    /**
     * A package that passes the composition rule: exactly 30/35/45 items.
     */
    public function complete(): static
    {
        return $this->afterCreating(function (ExamPackage $package): void {
            foreach (QuestionCategory::cases() as $category) {
                for ($i = 0; $i < $category->itemCount(); $i++) {
                    $package->addQuestion(Question::factory()->category($category)->create());
                }
            }
        });
    }
}
```

- [ ] **Step 5: Write the failing test**

`tests/Feature/Domain/PackageCompositionTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Support\PackageComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PackageCompositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_package_holds_exactly_thirty_thirty_five_forty_five(): void
    {
        $package = ExamPackage::factory()->complete()->create();

        $report = PackageComposition::report($package);

        foreach (['twk' => 30, 'tiu' => 35, 'tkp' => 45] as $category => $expected) {
            $this->assertSame($expected, $report[$category]['present'], "{$category} count");
            $this->assertTrue($report[$category]['complete']);
        }

        $this->assertSame(110, $package->questions()->count());
        $this->assertTrue(PackageComposition::isComplete($package));
        $this->assertSame('', PackageComposition::shortfallMessage($package));
    }

    public function test_a_package_one_item_short_is_not_complete(): void
    {
        $package = ExamPackage::factory()->complete()->create();
        $package->questions()->detach($package->questions()->first()->id);

        $this->assertFalse(PackageComposition::isComplete($package));
        $this->assertSame('TWK kurang 1.', PackageComposition::shortfallMessage($package));
    }

    public function test_a_wrong_category_split_is_not_complete_even_at_one_hundred_and_ten_items(): void
    {
        $package = ExamPackage::factory()->create();

        foreach (['twk' => 31, 'tiu' => 34, 'tkp' => 45] as $category => $count) {
            $enum = QuestionCategory::from($category);
            for ($i = 0; $i < $count; $i++) {
                $package->addQuestion(Question::factory()->category($enum)->create());
            }
        }

        $this->assertSame(110, $package->questions()->count());
        $this->assertFalse(PackageComposition::isComplete($package));
        $this->assertSame('TWK lebih 1, TIU kurang 1.', PackageComposition::shortfallMessage($package));
    }

    public function test_positions_are_assigned_in_attach_order_and_a_question_never_repeats(): void
    {
        $package = ExamPackage::factory()->create();
        $first = Question::factory()->twk()->create();
        $second = Question::factory()->twk()->create();

        $package->addQuestion($first);
        $package->addQuestion($second);

        $this->assertSame([1, 2], $package->questions()->get()->pluck('pivot.position')->all());
        $this->assertSame([$first->id, $second->id], $package->questions()->get()->pluck('id')->all());
    }
}
```

Note: `shortfallMessage` must therefore say "lebih" when a category is over its required count. Adjust Step 3's implementation accordingly before running:

```php
        foreach (self::report($package) as $row) {
            if ($row['complete']) {
                continue;
            }

            $missing[] = $row['present'] > $row['required']
                ? $row['label'].' lebih '.($row['present'] - $row['required'])
                : $row['label'].' kurang '.$row['missing'];
        }
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test --filter=PackageCompositionTest`
Expected: FAIL — `Class "App\Models\ExamPackage" not found`.

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=PackageCompositionTest`
Expected: PASS, 4 tests. If `test_positions_are_assigned_in_attach_order` fails on ordering, the pivot `orderBy` in `questions()` is missing — it must order by `exam_package_question.position`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_11_15_000004_create_exam_packages_table.php database/migrations/2026_11_15_000005_create_exam_package_question_table.php app/Models/ExamPackage.php database/factories/ExamPackageFactory.php app/Support/PackageComposition.php tests/Feature/Domain/PackageCompositionTest.php
git commit -m "feat(exam): packages, composition rules and the 30/35/45 check"
```

---

### Task 4: Attempt schema — the sitting, its answers and its subtopic rollup

**Files:**
- Create: `database/migrations/2026_11_15_000006_create_exam_attempts_table.php`
- Create: `database/migrations/2026_11_15_000007_create_exam_attempt_answers_table.php`
- Create: `database/migrations/2026_11_15_000008_create_exam_attempt_subtopics_table.php`
- Create: `app/Models/ExamAttempt.php`, `app/Models/ExamAttemptAnswer.php`, `app/Models/ExamAttemptSubtopic.php`
- Create: `database/factories/ExamAttemptFactory.php`
- Test: `tests/Feature/Domain/ExamAttemptSchemaTest.php`

**Interfaces:**
- Consumes: `ExamPackage`, `Question`, `QuestionOption`, `AttemptStatus`, `User`.
- Produces: `ExamAttempt::user()`, `::package()`, `::answers()`, `::subtopics()`, `::currentQuestion()`, `::isOverdue()`, `::remainingSeconds()`, `::elapsedSeconds()`, `::answeredCount()`, `::totalItems()`, `::isFinished()`, `::selectedOptionFor(Question $question): ?int`, `::active_key` uniqueness; `ExamAttemptAnswer::question()`, `::option()`; `ExamAttemptSubtopic` fields; `ExamAttemptFactory::forUserAndPackage(User, ExamPackage)`, `::overdue()`, `::submitted()`.

- [ ] **Step 1: Write the three migrations**

`database/migrations/2026_11_15_000006_create_exam_attempts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_package_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('in_progress');

            // The clock lives on the server: started_at plus the package duration.
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();

            // Timing per item: the open interval is (current_question_id -> last_activity_at).
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->timestamp('last_activity_at');

            // Result, frozen at submit. Nullable because an open attempt has none.
            $table->unsignedSmallInteger('twk_score')->nullable();
            $table->unsignedSmallInteger('tiu_score')->nullable();
            $table->unsignedSmallInteger('tkp_score')->nullable();
            $table->unsignedSmallInteger('total_score')->nullable();

            // Set only while the attempt is open, so the database itself guarantees
            // one open sitting per participant per package. Nullable, so finished
            // attempts are unconstrained and a participant may retake the package.
            $table->string('active_key')->nullable()->unique();

            $table->timestamps();

            $table->index(['user_id', 'exam_package_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
```

`database/migrations/2026_11_15_000007_create_exam_attempt_answers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            // Null means the item was opened but no option was chosen.
            $table->foreignId('selected_option_id')->nullable()->constrained('question_options')->nullOnDelete();

            $table->boolean('is_correct')->nullable();
            $table->unsignedTinyInteger('score_awarded')->nullable();
            $table->unsignedSmallInteger('seconds_spent')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            // One row per question per attempt: this is what makes auto-save safe to
            // fire repeatedly — repeats collapse instead of stacking.
            $table->unique(['exam_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
    }
};
```

`database/migrations/2026_11_15_000008_create_exam_attempt_subtopics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_subtopics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('subcategory');
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('wrong')->default(0);
            $table->unsignedSmallInteger('blank')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'subcategory']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_subtopics');
    }
};
```

- [ ] **Step 2: Write the `ExamAttempt` model**

`app/Models/ExamAttempt.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use Database\Factories\ExamAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'exam_package_id', 'status', 'started_at', 'expires_at', 'submitted_at',
    'current_question_id', 'last_activity_at', 'twk_score', 'tiu_score', 'tkp_score',
    'total_score', 'active_key',
])]
class ExamAttempt extends Model
{
    /** @use HasFactory<ExamAttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'twk_score' => 'int',
            'tiu_score' => 'int',
            'tkp_score' => 'int',
            'total_score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ExamPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ExamPackage::class, 'exam_package_id');
    }

    /**
     * @return HasMany<ExamAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }

    /**
     * @return HasMany<ExamAttemptSubtopic, $this>
     */
    public function subtopics(): HasMany
    {
        return $this->hasMany(ExamAttemptSubtopic::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    /**
     * Timestamp subtraction rather than Carbon::diffInSeconds(): the sign
     * convention changed in Carbon 3 and this is a timed exam.
     */
    public function remainingSeconds(): int
    {
        return max(0, $this->expires_at->getTimestamp() - now()->getTimestamp());
    }

    public function elapsedSeconds(): int
    {
        return max(0, now()->getTimestamp() - $this->started_at->getTimestamp());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->expires_at->getTimestamp() <= now()->getTimestamp();
    }

    public function isFinished(): bool
    {
        return ! $this->status->isOpen();
    }

    public function totalItems(): int
    {
        return $this->package->questions()->count();
    }

    public function answeredCount(): int
    {
        return $this->answers()->whereNotNull('selected_option_id')->count();
    }

    public function flaggedCount(): int
    {
        return $this->answers()->where('is_flagged', true)->count();
    }

    /** The chosen option id for one item, or null when it was skipped. */
    public function selectedOptionFor(Question $question): ?int
    {
        return $this->answers
            ->firstWhere('question_id', $question->id)
            ?->selected_option_id;
    }

    public function scoreFor(QuestionCategory $category): int
    {
        return (int) match ($category) {
            QuestionCategory::Twk => $this->twk_score,
            QuestionCategory::Tiu => $this->tiu_score,
            QuestionCategory::Tkp => $this->tkp_score,
        };
    }

    public function hasPassed(): bool
    {
        if (! $this->isFinished()) {
            return false;
        }

        foreach (QuestionCategory::cases() as $category) {
            if ($this->scoreFor($category) < $category->threshold()) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 3: Write the two child models**

`app/Models/ExamAttemptAnswer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'exam_attempt_id', 'question_id', 'selected_option_id',
    'is_correct', 'score_awarded', 'seconds_spent', 'is_flagged', 'answered_at',
])]
class ExamAttemptAnswer extends Model
{
    /** @use HasFactory<ExamAttemptAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'bool',
            'score_awarded' => 'int',
            'seconds_spent' => 'int',
            'is_flagged' => 'bool',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<QuestionOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'selected_option_id');
    }

    public function isBlank(): bool
    {
        return $this->selected_option_id === null;
    }
}
```

`app/Models/ExamAttemptSubtopic.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionCategory;
use Database\Factories\ExamAttemptSubtopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_attempt_id', 'category', 'subcategory', 'correct', 'wrong', 'blank', 'score'])]
class ExamAttemptSubtopic extends Model
{
    /** @use HasFactory<ExamAttemptSubtopicFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => QuestionCategory::class,
            'correct' => 'int',
            'wrong' => 'int',
            'blank' => 'int',
            'score' => 'int',
        ];
    }

    /**
     * @return BelongsTo<ExamAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function label(): string
    {
        return $this->category->subcategoryLabel($this->subcategory);
    }
}
```

- [ ] **Step 4: Write the attempt factory and its two child factories**

`database/factories/ExamAttemptFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now();

        return [
            'user_id' => User::factory(),
            'exam_package_id' => ExamPackage::factory(),
            'status' => AttemptStatus::InProgress->value,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes((int) config('cpns.duration_minutes')),
            'last_activity_at' => $startedAt,
            'active_key' => null,
        ];
    }

    /**
     * An open attempt that is already past its expiry: what the read path must
     * close and grade without any scheduler running.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => now()->subMinutes((int) config('cpns.duration_minutes') + 5),
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttemptStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    public function forUserAndPackage(User $user, ExamPackage $package): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
            'exam_package_id' => $package->id,
        ]);
    }
}
```

`database/factories/ExamAttemptAnswerFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptAnswer>
 */
class ExamAttemptAnswerFactory extends Factory
{
    protected $model = ExamAttemptAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'question_id' => Question::factory(),
            'selected_option_id' => null,
            'seconds_spent' => fake()->numberBetween(5, 180),
            'is_flagged' => false,
            'answered_at' => now(),
        ];
    }
}
```

`database/factories/ExamAttemptSubtopicFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptSubtopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttemptSubtopic>
 */
class ExamAttemptSubtopicFactory extends Factory
{
    protected $model = ExamAttemptSubtopic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_attempt_id' => ExamAttempt::factory(),
            'category' => 'twk',
            'subcategory' => 'pilar_negara',
            'correct' => 0,
            'wrong' => 0,
            'blank' => 0,
            'score' => 0,
        ];
    }
}
```

- [ ] **Step 5: Write the failing test**

`tests/Feature/Domain/ExamAttemptSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamAttemptSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_clock_starts_from_the_package_duration(): void
    {
        $package = ExamPackage::factory()->create(['duration_minutes' => 100]);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        $this->assertSame(3600, $attempt->expires_at->getTimestamp() - $attempt->started_at->getTimestamp());
        $this->assertSame(3600, $attempt->remainingSeconds());
        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertFalse($attempt->isFinished());
    }

    public function test_an_overdue_attempt_reports_no_time_left_and_explains_itself(): void
    {
        $attempt = ExamAttempt::factory()->overdue()->create();

        $this->assertSame(0, $attempt->remainingSeconds());
        $this->assertTrue($attempt->isOverdue());
        $this->assertTrue($attempt->status->isOpen());
    }

    public function test_one_question_can_only_be_answered_once_per_attempt(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();
        $package->addQuestion($question);
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);

        $this->expectException(QueryException::class);

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $question->id,
        ]);
    }

    public function test_only_one_open_attempt_per_participant_and_package_is_possible(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->create([
            'active_key' => $user->id.':'.$package->id,
        ]);

        $this->expectException(QueryException::class);

        ExamAttempt::factory()->forUserAndPackage($user, $package)->create([
            'active_key' => $user->id.':'.$package->id,
        ]);
    }

    public function test_finished_attempts_do_not_block_a_retake(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create(['active_key' => null]);
        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create(['active_key' => null]);

        $this->assertSame(2, $user->attempts()->count());
    }

    public function test_answered_count_ignores_items_that_were_opened_but_not_answered(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(2)->create();
        $package->questions()->attach($questions->pluck('id')->all());
        $attempt = ExamAttempt::factory()->forUserAndPackage(User::factory()->create(), $package)->create();

        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questions[0]->id,
            'selected_option_id' => $questions[0]->options->first()->id,
        ]);
        ExamAttemptAnswer::factory()->create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questions[1]->id,
            'selected_option_id' => null,
        ]);

        $this->assertSame(2, $attempt->totalItems());
        $this->assertSame(1, $attempt->answeredCount());
    }
}
```

Note: the test above attaches two questions with `attach()` without a `position`, which the pivot allows (nullable? no — `position` is `unsignedSmallInteger` and non-nullable). Use instead:

```php
        foreach ($questions as $question) {
            $package->addQuestion($question);
        }
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test --filter=ExamAttemptSchemaTest`
Expected: FAIL — `Class "App\Models\ExamAttempt" not found`.

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=ExamAttemptSchemaTest`
Expected: PASS, 6 tests.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_11_15_000006_create_exam_attempts_table.php database/migrations/2026_11_15_000007_create_exam_attempt_answers_table.php database/migrations/2026_11_15_000008_create_exam_attempt_subtopics_table.php app/Models/ExamAttempt.php app/Models/ExamAttemptAnswer.php app/Models/ExamAttemptSubtopic.php database/factories tests/Feature/Domain/ExamAttemptSchemaTest.php
git commit -m "feat(exam): attempt, answer and subtopic tables with the unique row per item"
```

### Task 5: ExamGrader — the one place scoring happens

**Files:**
- Create: `app/Services/Exam/ExamGrader.php`
- Create: `tests/Concerns/SitsExam.php`
- Test: `tests/Feature/Exam/ExamGraderTest.php`

**Interfaces:**
- Consumes: `ExamAttempt`, `ExamPackage::questions()`, `QuestionCategory::scorePerCorrect()`, `config('cpns.subtests.*.wrong')`.
- Produces: `ExamGrader::grade(ExamAttempt $attempt, bool $expired = false): ExamAttempt` — freezes `is_correct`/`score_awarded` on every answer row, writes one `exam_attempt_subtopics` row per (subtest, subtopic), sets the four score columns, flips status to `Submitted` or `Expired`, clears `active_key`. Idempotent: a call on an already-finished attempt returns it untouched.
- Produces (test helper): `Tests\Concerns\SitsExam::answerEveryQuestion(ExamAttempt $attempt, string $strategy)` with strategies `best`, `worst`, `wrong`, `blank`.

- [ ] **Step 1: Write the grader**

`app/Services/Exam/ExamGrader.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Scoring happens here and nowhere else, exactly once per sitting.
 *
 * TWK and TIU award the blueprint's per-correct score for the keyed option and
 * zero otherwise. TKP awards the chosen option's weight (1-5) and zero when
 * nothing was chosen — which is why blanking a TKP item is always a loss and
 * never a safe move.
 *
 * The result is frozen onto the answer rows and rolled up per subtopic. A later
 * edit to a question therefore cannot move a finished participant's score, while
 * the item's text and explanation stay live in the bank.
 *
 * TKP has no wrong answers, so for a TKP subtopic the `correct` column counts
 * answered items and `wrong` stays 0. The results page labels it that way.
 */
final class ExamGrader
{
    public function grade(ExamAttempt $attempt, bool $expired = false): ExamAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        $questions = $attempt->package->questions()->with('options')->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        /** @var array<string, int> $scores */
        $scores = [
            QuestionCategory::Twk->value => 0,
            QuestionCategory::Tiu->value => 0,
            QuestionCategory::Tkp->value => 0,
        ];

        /** @var array<string, array<string, mixed>> $rollup */
        $rollup = [];

        foreach ($questions as $question) {
            $category = $question->category;
            $bucketKey = $category->value.'|'.$question->subcategory;

            $rollup[$bucketKey] ??= [
                'category' => $category->value,
                'subcategory' => $question->subcategory,
                'correct' => 0,
                'wrong' => 0,
                'blank' => 0,
                'score' => 0,
            ];

            $answer = $answers->get($question->id);
            $option = ($answer !== null && $answer->selected_option_id !== null)
                ? $question->options->firstWhere('id', $answer->selected_option_id)
                : null;

            if ($option === null) {
                $rollup[$bucketKey]['blank']++;

                $answer?->forceFill(['is_correct' => null, 'score_awarded' => 0])->save();

                continue;
            }

            if ($category === QuestionCategory::Tkp) {
                $awarded = (int) ($option->score ?? 0);
                $rollup[$bucketKey]['correct']++;
                $answer->forceFill(['is_correct' => null, 'score_awarded' => $awarded])->save();
            } else {
                $isCorrect = (bool) $option->is_correct;
                $awarded = $isCorrect
                    ? $category->scorePerCorrect()
                    : (int) $category->config('wrong', 0);

                $rollup[$bucketKey][$isCorrect ? 'correct' : 'wrong']++;
                $answer->forceFill(['is_correct' => $isCorrect, 'score_awarded' => $awarded])->save();
            }

            $rollup[$bucketKey]['score'] += $awarded;
            $scores[$category->value] += $awarded;
        }

        DB::transaction(function () use ($attempt, $rollup, $scores, $expired): void {
            $attempt->subtopics()->delete();
            $attempt->subtopics()->createMany(array_values($rollup));

            $attempt->forceFill([
                'status' => $expired ? AttemptStatus::Expired : AttemptStatus::Submitted,
                'submitted_at' => now(),
                // Freeing the unique key is what lets the participant retake it.
                'active_key' => null,
                'twk_score' => $scores[QuestionCategory::Twk->value],
                'tiu_score' => $scores[QuestionCategory::Tiu->value],
                'tkp_score' => $scores[QuestionCategory::Tkp->value],
                'total_score' => array_sum($scores),
            ])->save();
        });

        return $attempt->refresh();
    }
}
```

- [ ] **Step 2: Write the test helper that sits an exam**

`tests/Concerns/SitsExam.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ExamAttempt;
use App\Models\Question;

/**
 * Answering a 110-item paper by hand in every test would bury the assertions, so
 * tests describe the sitting in one of four strategies and this trait does the
 * clicking. `best` = the key for TWK/TIU and weight 5 for TKP; `worst` = a wrong
 * option for TWK/TIU and weight 1 for TKP; `wrong` = only the misses are filled
 * in; `blank` = every item is opened and left empty.
 */
trait SitsExam
{
    protected function answerEveryQuestion(ExamAttempt $attempt, string $strategy = 'best'): void
    {
        foreach ($attempt->package->questions()->with('options')->get() as $question) {
            $option = $this->optionFor($question, $strategy);

            $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'selected_option_id' => $option?->id,
                    'answered_at' => now(),
                ],
            );
        }
    }

    /** Fill in exactly the first $count items of the given subtest, correctly. */
    protected function answerCorrectly(ExamAttempt $attempt, string $category, int $count): void
    {
        $questions = $attempt->package->questions()
            ->where('questions.category', $category)
            ->with('options')
            ->limit($count)
            ->get();

        foreach ($questions as $question) {
            $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'selected_option_id' => $question->options->firstWhere('is_correct', true)?->id,
                    'answered_at' => now(),
                ],
            );
        }
    }

    private function optionFor(Question $question, string $strategy): mixed
    {
        return match ($strategy) {
            'blank' => null,
            'best' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', true)
                : $question->options->sortByDesc('score')->first(),
            'worst' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', false)
                : $question->options->sortBy('score')->first(),
            'wrong' => $question->category->requiresKey()
                ? $question->options->firstWhere('is_correct', false)
                : null,
            default => $question->options->first(),
        };
    }
}
```

- [ ] **Step 3: Write the failing test**

`tests/Feature/Exam/ExamGraderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\ExamAttemptSubtopic;
use App\Models\User;
use App\Services\Exam\ExamGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ExamGraderTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private function paper(): ExamAttempt
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->create();

        return ExamAttempt::factory()->forUserAndPackage($user, $package)->create();
    }

    public function test_a_perfect_paper_scores_the_full_five_hundred_and_fifty(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(150, $graded->twk_score);
        $this->assertSame(175, $graded->tiu_score);
        $this->assertSame(225, $graded->tkp_score);
        $this->assertSame(550, $graded->total_score);
        $this->assertSame(AttemptStatus::Submitted, $graded->status);
        $this->assertNotNull($graded->submitted_at);
        $this->assertNull($graded->active_key);
        $this->assertTrue($graded->hasPassed());
    }

    public function test_wrong_answers_score_nothing_and_the_worst_tkp_choice_still_scores(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'worst');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(0, $graded->twk_score);
        $this->assertSame(0, $graded->tiu_score);
        $this->assertSame(45, $graded->tkp_score, 'TKP worst options score 1 each');
        $this->assertSame(45, $graded->total_score);
        $this->assertFalse($graded->hasPassed());
    }

    public function test_blank_answers_score_zero_including_tkp(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'blank');

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(0, $graded->twk_score);
        $this->assertSame(0, $graded->tiu_score);
        $this->assertSame(0, $graded->tkp_score);
        $this->assertSame(0, $graded->total_score);
    }

    public function test_the_three_thresholds_are_independent_not_summed(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');
        $this->answerCorrectly($attempt, 'tiu', 14);

        // Wipe TIU and leave only 14 correct: 14 x 5 = 70, below the 80 threshold.
        $attempt->answers()
            ->whereIn('question_id', $attempt->package->questions()->where('questions.category', 'tiu')->pluck('id'))
            ->whereNotIn('question_id', $attempt->package->questions()->where('questions.category', 'tiu')->limit(14)->pluck('id'))
            ->update(['selected_option_id' => null]);

        $graded = app(ExamGrader::class)->grade($attempt);

        $this->assertSame(70, $graded->tiu_score);
        $this->assertSame(150, $graded->twk_score);
        $this->assertSame(225, $graded->tkp_score);
        $this->assertSame(445, $graded->total_score);
        $this->assertFalse($graded->hasPassed(), 'A 445 total still fails when one subtest is under its threshold');
    }

    public function test_the_frozen_result_lands_on_every_answer_row(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        app(ExamGrader::class)->grade($attempt);

        $twkAnswers = $attempt->answers()
            ->whereIn('question_id', $attempt->package->questions()->where('questions.category', 'twk')->pluck('id'))
            ->get();

        $this->assertCount(30, $twkAnswers);
        $this->assertTrue($twkAnswers->every(fn ($answer): bool => $answer->is_correct === true));
        $this->assertTrue($twkAnswers->every(fn ($answer): bool => $answer->score_awarded === 5));
    }

    public function test_it_rolls_the_result_up_per_subtopic(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        app(ExamGrader::class)->grade($attempt);

        $subtopics = $attempt->subtopics()->get();

        $this->assertGreaterThan(0, $subtopics->count());
        $this->assertSame(
            $attempt->package->questions()->count(),
            (int) $subtopics->sum('correct') + (int) $subtopics->sum('wrong') + (int) $subtopics->sum('blank'),
        );
        $this->assertSame(550, (int) $subtopics->sum('score'));
        $this->assertSame(0, $subtopics->where('wrong', '>', 0)->count());
    }

    public function test_grading_twice_changes_nothing(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $first = app(ExamGrader::class)->grade($attempt);
        $rows = ExamAttemptSubtopic::query()->where('exam_attempt_id', $attempt->id)->count();
        $second = app(ExamGrader::class)->grade($first);

        $this->assertGreaterThan(0, $rows);
        $this->assertSame($first->total_score, $second->total_score);
        // A finished attempt is returned untouched, so the rollup is not rewritten.
        $this->assertSame($rows, ExamAttemptSubtopic::query()->where('exam_attempt_id', $attempt->id)->count());
    }

    public function test_an_expired_sitting_is_recorded_as_expired_not_submitted(): void
    {
        $attempt = $this->paper();
        $this->answerEveryQuestion($attempt, 'best');

        $graded = app(ExamGrader::class)->grade($attempt, expired: true);

        $this->assertSame(AttemptStatus::Expired, $graded->status);
        $this->assertSame(550, $graded->total_score);
    }
}
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test --filter=ExamGraderTest`
Expected: FAIL — `Class "App\Services\Exam\ExamGrader" not found`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ExamGraderTest`
Expected: PASS, 8 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Exam/ExamGrader.php tests/Concerns/SitsExam.php tests/Feature/Exam/ExamGraderTest.php
git commit -m "feat(exam): freeze scoring at submit and roll it up per subtopic"
```

---

### Task 6: AnswerRecorder — idempotent auto-save and per-item timing

**Files:**
- Create: `app/Services/Exam/AnswerRecorder.php`
- Test: `tests/Feature/Exam/AnswerRecorderTest.php`

**Interfaces:**
- Consumes: `ExamGrader::grade()`, `ExamAttempt`, `Question`, `config('cpns.timing_cap_seconds')`.
- Produces: `AnswerRecorder::record(ExamAttempt $attempt, int $questionId, ?int $optionId, bool $flagged = false): ExamAttemptAnswer`; `AnswerRecorder::openQuestion(ExamAttempt $attempt, Question $question): void`; `AnswerRecorder::closeOpenInterval(ExamAttempt $attempt): void`. Throws `Illuminate\Validation\ValidationException` when the attempt is finished, when it has run out of time (after grading it as expired), when the question is not in the package, or when the option does not belong to the question.

- [ ] **Step 1: Write the recorder**

`app/Services/Exam/AnswerRecorder.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes one answer. Every call names exactly one question, so a lost, repeated or
 * retried request can only ever affect that item — the browser never uploads a
 * whole exam state blob and the server never accepts one.
 *
 * The upsert keyed on (attempt, question) is what makes clicking an option twice,
 * tapping fast on a slow connection, or retrying after a failure collapse onto a
 * single row instead of stacking duplicates.
 */
final class AnswerRecorder
{
    public function __construct(private readonly ExamGrader $grader) {}

    public function record(ExamAttempt $attempt, int $questionId, ?int $optionId, bool $flagged = false): ExamAttemptAnswer
    {
        if (! $attempt->status->isOpen()) {
            throw ValidationException::withMessages(['attempt' => 'Ujian ini sudah selesai.']);
        }

        if ($attempt->isOverdue()) {
            // A late write is not an error the participant can fix, so the sitting
            // is closed and graded on the spot rather than silently dropped.
            $this->grader->grade($attempt, expired: true);

            throw ValidationException::withMessages(['attempt' => 'Waktu ujian sudah habis.']);
        }

        $question = $attempt->package->questions()->whereKey($questionId)->first();

        if ($question === null) {
            throw ValidationException::withMessages(['question_id' => 'Soal itu bukan bagian dari paket ini.']);
        }

        if ($optionId !== null && ! $question->options()->whereKey($optionId)->exists()) {
            throw ValidationException::withMessages(['selected_option_id' => 'Opsi jawaban itu tidak dikenali.']);
        }

        $this->closeOpenInterval($attempt);

        return DB::transaction(function () use ($attempt, $question, $optionId, $flagged): ExamAttemptAnswer {
            $answer = $attempt->answers()->updateOrCreate(
                ['question_id' => $question->id],
                [
                    'selected_option_id' => $optionId,
                    'is_flagged' => $flagged,
                    'answered_at' => now(),
                ],
            );

            $attempt->forceFill([
                'current_question_id' => $question->id,
                'last_activity_at' => now(),
            ])->save();

            return $answer;
        });
    }

    /**
     * The participant moved to another item without answering. The seconds already
     * spent still belong to the item they left, so the interval is closed here and
     * a new one opens on the item they are looking at.
     */
    public function openQuestion(ExamAttempt $attempt, Question $question): void
    {
        if (! $attempt->status->isOpen() || $attempt->isOverdue()) {
            return;
        }

        $this->closeOpenInterval($attempt);

        $attempt->forceFill([
            'current_question_id' => $question->id,
            'last_activity_at' => now(),
        ])->save();
    }

    /**
     * Adds the seconds since the last write to the item that was on screen. Capped,
     * because a tab left open overnight would otherwise hand one item half an hour
     * and make the timing report nonsense.
     */
    public function closeOpenInterval(ExamAttempt $attempt): void
    {
        $questionId = $attempt->current_question_id;
        $since = $attempt->last_activity_at;

        if ($questionId === null || $since === null) {
            return;
        }

        $seconds = min(
            max(0, now()->getTimestamp() - $since->getTimestamp()),
            (int) config('cpns.timing_cap_seconds'),
        );

        if ($seconds === 0) {
            return;
        }

        $answer = $attempt->answers()->firstOrNew(['question_id' => $questionId]);
        $answer->seconds_spent = (int) $answer->seconds_spent + $seconds;
        $answer->save();
    }
}
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Exam/AnswerRecorderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Services\Exam\AnswerRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AnswerRecorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ExamAttempt, 1: array<int, Question>}
     */
    private function sitting(int $items = 3): array
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count($items)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $attempt = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        return [$attempt, $questions->all()];
    }

    public function test_clicking_the_same_option_twice_leaves_one_row(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);
        $option = $questions[0]->options->first();

        $recorder->record($attempt, $questions[0]->id, $option->id);
        $recorder->record($attempt->refresh(), $questions[0]->id, $option->id);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
        $this->assertSame($option->id, $attempt->refresh()->selectedOptionFor($questions[0]));
    }

    public function test_changing_the_option_updates_the_same_row(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);
        $first = $questions[0]->options->first();
        $second = $questions[0]->options->last();

        $recorder->record($attempt, $questions[0]->id, $first->id);
        $recorder->record($attempt->refresh(), $questions[0]->id, $second->id);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
        $this->assertSame($second->id, $attempt->refresh()->selectedOptionFor($questions[0]));
    }

    public function test_skipping_records_a_row_with_no_selection(): void
    {
        [$attempt, $questions] = $this->sitting();

        app(AnswerRecorder::class)->record($attempt, $questions[0]->id, null);

        $this->assertNull($attempt->refresh()->selectedOptionFor($questions[0]));
        $this->assertSame(0, $attempt->answeredCount());
        $this->assertSame(1, $attempt->answers()->count());
    }

    public function test_an_item_from_another_package_is_refused(): void
    {
        [$attempt] = $this->sitting();
        $stranger = Question::factory()->twk()->create();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt, $stranger->id, $stranger->options->first()->id);
    }

    public function test_an_option_that_belongs_to_another_question_is_refused(): void
    {
        [$attempt, $questions] = $this->sitting();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt, $questions[0]->id, $questions[1]->options->first()->id);
    }

    public function test_a_finished_attempt_accepts_nothing(): void
    {
        [$attempt, $questions] = $this->sitting();
        $attempt->forceFill(['status' => AttemptStatus::Submitted])->save();

        $this->expectException(ValidationException::class);

        app(AnswerRecorder::class)->record($attempt->refresh(), $questions[0]->id, $questions[0]->options->first()->id);
    }

    public function test_a_late_write_closes_and_grades_the_sitting(): void
    {
        [$attempt, $questions] = $this->sitting();
        $attempt->forceFill([
            'started_at' => now()->subMinutes(101),
            'expires_at' => now()->subMinute(),
        ])->save();

        try {
            app(AnswerRecorder::class)->record($attempt->refresh(), $questions[0]->id, $questions[0]->options->first()->id);
            $this->fail('A late write should have been refused.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
        $this->assertNotNull($attempt->submitted_at);
    }

    public function test_timing_accrues_on_the_item_that_was_on_screen_and_is_capped(): void
    {
        [$attempt, $questions] = $this->sitting();
        $recorder = app(AnswerRecorder::class);

        $recorder->openQuestion($attempt, $questions[0]);
        $attempt->forceFill(['last_activity_at' => now()->subSeconds(42)])->save();
        $recorder->openQuestion($attempt->refresh(), $questions[1]);

        $this->assertSame(42, $attempt->refresh()->answers()->where('question_id', $questions[0]->id)->value('seconds_spent'));

        $attempt->forceFill(['last_activity_at' => now()->subHour()])->save();
        $recorder->closeOpenInterval($attempt->refresh());

        $this->assertSame(
            (int) config('cpns.timing_cap_seconds'),
            (int) $attempt->refresh()->answers()->where('question_id', $questions[1]->id)->value('seconds_spent'),
        );
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=AnswerRecorderTest`
Expected: FAIL — `Class "App\Services\Exam\AnswerRecorder" not found`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=AnswerRecorderTest`
Expected: PASS, 8 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Exam/AnswerRecorder.php tests/Feature/Exam/AnswerRecorderTest.php
git commit -m "feat(exam): idempotent answer saving with per-item timing"
```

---

### Task 7: AttemptManager — start, resume, restart, finish, expire on read

**Files:**
- Create: `app/Services/Exam/AttemptManager.php`
- Test: `tests/Feature/Exam/AttemptManagerTest.php`

**Interfaces:**
- Consumes: `ExamGrader`, `AnswerRecorder::closeOpenInterval()`, `ExamAttempt`, `User`.
- Produces: `AttemptManager::startOrResume(User $user, ExamPackage $package): ExamAttempt`; `AttemptManager::restart(User $user, ExamPackage $package): ExamAttempt`; `AttemptManager::finish(ExamAttempt $attempt): ExamAttempt`; `AttemptManager::expireOverdue(User $user): int`; `AttemptManager::expireAllOverdue(): int`. `finish()` throws `ValidationException` while any item has no selection.

- [ ] **Step 1: Write the manager**

`app/Services/Exam/AttemptManager.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the life of a sitting: it starts, resumes, restarts, finishes and closes
 * attempts. Time is never taken from the browser — the clock is `started_at` plus
 * the package duration, and expiry is evaluated on the read path, which is why
 * the application is correct with no scheduler running at all.
 */
final class AttemptManager
{
    public function __construct(
        private readonly ExamGrader $grader,
        private readonly AnswerRecorder $recorder,
    ) {}

    /**
     * Starting twice is resuming: someone who closed the browser on item 40 comes
     * back to item 40 with the clock still running, never to a fresh paper.
     */
    public function startOrResume(User $user, ExamPackage $package): ExamAttempt
    {
        $this->expireOverdue($user);

        $existing = $user->attempts()
            ->where('exam_package_id', $package->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $startedAt = now();

        return ExamAttempt::create([
            'user_id' => $user->id,
            'exam_package_id' => $package->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => $startedAt,
            'expires_at' => $startedAt->copy()->addMinutes($package->duration_minutes),
            'last_activity_at' => $startedAt,
            'active_key' => $user->id.':'.$package->id,
        ]);
    }

    /**
     * The explicit "start over" action. The abandoned sitting is closed and graded
     * as expired rather than deleted: it happened, and its scores are its own.
     */
    public function restart(User $user, ExamPackage $package): ExamAttempt
    {
        $open = $user->attempts()
            ->where('exam_package_id', $package->id)
            ->where('status', AttemptStatus::InProgress)
            ->latest('id')
            ->first();

        if ($open !== null) {
            $this->recorder->closeOpenInterval($open);
            $this->grader->grade($open, expired: true);
        }

        return $this->startOrResume($user, $package);
    }

    /**
     * Finishing is refused while anything is still unanswered — in the service,
     * not only by disabling a button. A sitting that ran out of time is graded
     * without that check, because there is nobody left to answer.
     */
    public function finish(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }

        if ($attempt->isOverdue()) {
            return $this->grader->grade($attempt, expired: true);
        }

        $total = $attempt->totalItems();
        $answered = $attempt->answeredCount();

        if ($answered < $total) {
            throw ValidationException::withMessages([
                'attempt' => 'Masih ada '.($total - $answered).' soal yang belum dijawab.',
            ]);
        }

        $this->recorder->closeOpenInterval($attempt);

        return $this->grader->grade($attempt);
    }

    /** Closes and grades whatever this participant left behind. */
    public function expireOverdue(User $user): int
    {
        $overdue = $user->attempts()
            ->where('status', AttemptStatus::InProgress)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $attempt) {
            $this->grader->grade($attempt, expired: true);
        }

        return $overdue->count();
    }

    /** The whole-table version, used by the optional scheduled sweep. */
    public function expireAllOverdue(): int
    {
        $overdue = ExamAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('expires_at', '<=', now())
            ->get();

        DB::transaction(function () use ($overdue): void {
            foreach ($overdue as $attempt) {
                $this->grader->grade($attempt, expired: true);
            }
        });

        return $overdue->count();
    }
}
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Exam/AttemptManagerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class AttemptManagerTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    private function manager(): AttemptManager
    {
        return app(AttemptManager::class);
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    public function test_starting_creates_a_sitting_that_expires_in_one_hundred_minutes(): void
    {
        $attempt = $this->manager()->startOrResume($this->user(), ExamPackage::factory()->create());

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame(6000, $attempt->remainingSeconds());
        $this->assertNotNull($attempt->active_key);
    }

    public function test_starting_again_resumes_the_same_sitting(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->create();

        $first = $this->manager()->startOrResume($user, $package);
        $second = $this->manager()->startOrResume($user, $package);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ExamAttempt::query()->count());
    }

    public function test_a_resumed_sitting_keeps_the_clock_it_was_given(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->create();

        $first = $this->manager()->startOrResume($user, $package);
        $first->forceFill(['started_at' => now()->subMinutes(30), 'expires_at' => now()->addMinutes(70)])->save();

        $resumed = $this->manager()->startOrResume($user, $package);

        $this->assertSame(4200, $resumed->remainingSeconds());
    }

    public function test_restarting_closes_the_old_sitting_as_expired_and_opens_a_new_one(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $old = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($old, 'best');

        $fresh = $this->manager()->restart($user, $package);

        $this->assertFalse($old->is($fresh));
        $this->assertSame(AttemptStatus::Expired, $old->refresh()->status);
        $this->assertSame(550, $old->total_score);
        $this->assertSame(AttemptStatus::InProgress, $fresh->status);
        $this->assertSame(0, $fresh->answeredCount());
        $this->assertSame(2, ExamAttempt::query()->count());
    }

    public function test_finishing_is_refused_while_one_item_is_blank(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $blank = $attempt->answers()->whereNotNull('selected_option_id')->first();
        $blank->forceFill(['selected_option_id' => null])->save();

        try {
            $this->manager()->finish($attempt->refresh());
            $this->fail('Finishing with a blank item should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('1 soal', $exception->getMessage());
        }

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }

    public function test_finishing_a_complete_sitting_grades_it(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $finished = $this->manager()->finish($attempt->refresh());

        $this->assertSame(AttemptStatus::Submitted, $finished->status);
        $this->assertSame(550, $finished->total_score);
        $this->assertNull($finished->active_key);
    }

    public function test_an_incomplete_sitting_that_ran_out_of_time_is_graded_as_expired(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = $this->manager()->startOrResume($user, $package);
        $this->answerCorrectly($attempt, 'twk', 30);
        $attempt->forceFill(['expires_at' => now()->subSecond()])->save();

        $finished = $this->manager()->finish($attempt->refresh());

        $this->assertSame(AttemptStatus::Expired, $finished->status);
        $this->assertSame(150, $finished->twk_score);
        $this->assertSame(150, $finished->total_score);
    }

    public function test_the_read_path_closes_an_overdue_sitting_with_no_scheduler(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->complete()->create();
        $attempt = ExamAttempt::factory()->forUserAndPackage($user, $package)->overdue()->create([
            'active_key' => $user->id.':'.$package->id,
            'current_question_id' => $package->questions()->first()->id,
            'last_activity_at' => now()->subMinutes(6),
        ]);
        $this->answerCorrectly($attempt, 'tiu', 35);

        $closed = $this->manager()->expireOverdue($user);

        $this->assertSame(1, $closed);
        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
        $this->assertSame(175, $attempt->tiu_score);
        $this->assertNull($attempt->active_key);
    }

    public function test_an_open_sitting_is_left_alone_by_the_read_path(): void
    {
        $user = $this->user();
        $package = ExamPackage::factory()->create();
        $attempt = $this->manager()->startOrResume($user, $package);

        $this->assertSame(0, $this->manager()->expireOverdue($user));
        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=AttemptManagerTest`
Expected: FAIL — `Class "App\Services\Exam\AttemptManager" not found`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=AttemptManagerTest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Exam/AttemptManager.php tests/Feature/Exam/AttemptManagerTest.php
git commit -m "feat(exam): start, resume, restart, finish and read-path expiry"
```

---

### Task 8: Accounts and roles — session auth written by hand

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`, `RegisterController.php`, `PasswordController.php`
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`, `app/Http/Middleware/EnsurePasswordIsChanged.php`
- Create: `resources/views/auth/login.blade.php`, `register.blade.php`, `password.blade.php`
- Modify: `routes/web.php`, `bootstrap/app.php`, `.env`, `.env.example`
- Test: `tests/Feature/Auth/AccountTest.php`

**Interfaces:**
- Consumes: `ContentRepository` (for `$site`), `UserRole`, `User::assignParticipantNumber()`, `Gate::before` from Task 1.
- Produces: routes `login`, `register`, `logout`, `password.edit`, `password.update`, `dashboard`; middleware aliases `admin` and `password.changed`; `LoginController`, `RegisterController`, `PasswordController`, `DashboardController`.

- [ ] **Step 1: Write the two middleware**

`app/Http/Middleware/EnsureUserIsAdmin.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin() === true, 403);

        return $next($request);
    }
}
```

`app/Http/Middleware/EnsurePasswordIsChanged.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only thing keeping a temporary password alive: without mail there is no
 * reset link to expire, so the account is held at the password form until it is
 * changed. The password page and logout stay reachable, or the user would be
 * trapped in a loop.
 */
final class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password === true
            && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Register the aliases and the guest redirect**

Replace the `withMiddleware(...)` block in `bootstrap/app.php` with:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);

        // Laravel's `auth` middleware already sends guests to the route named
        // `login`; stating it here keeps that contract visible in one place.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
```

and add the two imports at the top of the file:

```php
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsAdmin;
```

- [ ] **Step 3: Write the three auth controllers and the dashboard controller**

`app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function create(ContentRepository $content): View
    {
        return view('auth.login', ['site' => $content->site()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Throttled per email+IP rather than per IP alone, so one shared connection
        // cannot lock out the whole house.
        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        $limit = (int) config('cpns.password.login_attempts_per_minute');

        if (RateLimiter::tooManyAttempts($throttleKey, $limit)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '
                    .RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak cocok.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
```

`app/Http/Controllers/Auth/RegisterController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

final class RegisterController extends Controller
{
    public function create(ContentRepository $content): View
    {
        return view('auth.register', ['site' => $content->site()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $minLength = (int) config('cpns.password.min_length');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        // Email is deliberately asked for once: with no mailer an address cannot be
        // verified, and a typo is fixed faster from the admin panel than it is
        // prevented by making everyone type it twice.
        $user = User::create($data);

        $user->assignParticipantNumber();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
```

`app/Http/Controllers/Auth/PasswordController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class PasswordController extends Controller
{
    public function edit(Request $request, ContentRepository $content): View
    {
        return view('auth.password', [
            'site' => $content->site(),
            'forced' => $request->user()->must_change_password,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $minLength = (int) config('cpns.password.min_length');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min($minLength)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak cocok.']);
        }

        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Password sudah diganti.');
    }
}
```

`app/Http/Controllers/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttemptStatus;
use App\Models\ExamPackage;
use App\Services\Exam\AttemptManager;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The participant's own page: what is still running, what has been finished, and
 * the analysis for each finished sitting.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request, ContentRepository $content, AttemptManager $manager): View
    {
        $manager->expireOverdue($request->user());

        $attempts = $request->user()->attempts()
            ->with('package')
            ->latest('id')
            ->get();

        return view('exam.dashboard', [
            'site' => $content->site(),
            'open' => $attempts->where('status', AttemptStatus::InProgress),
            'finished' => $attempts->where('status', '!=', AttemptStatus::InProgress),
            'packages' => ExamPackage::query()
                ->where('status', ExamPackage::STATUS_PUBLISHED)
                ->orderBy('title')
                ->get(),
        ]);
    }
}
```

- [ ] **Step 4: Write the three auth views**

They use the marketing layout with the site content, exactly like `resources/views/home.blade.php` does. `resources/views/auth/login.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="auth" class="bg-surface px-5 py-14 lg:px-10 lg:py-20">
        <div class="mx-auto w-full max-w-[420px] rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Masuk</h1>
            <p class="mt-2 text-body text-muted">Lanjutkan latihan SKD kamu dan lihat analisa skornya.</p>

            @if ($errors->any())
                <p role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                    {{ $errors->first() }}
                </p>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password</span>
                    <input type="password" name="password" required autocomplete="current-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <label class="flex items-center gap-2 text-small text-muted">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-line">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark">
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-small text-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-brand hover:text-brand-dark">Daftar gratis</a>
            </p>
        </div>
    </section>
</x-layouts.app>
```

`resources/views/auth/register.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="auth" class="bg-surface px-5 py-14 lg:px-10 lg:py-20">
        <div class="mx-auto w-full max-w-[420px] rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Daftar akun</h1>
            <p class="mt-2 text-body text-muted">Gratis. Semua paket tryout terbuka begitu akunnya jadi.</p>

            @if ($errors->any())
                <p role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                    {{ $errors->first() }}
                </p>
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Nama lengkap</span>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password</span>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                    <span class="mt-1.5 block text-caption text-muted">Minimal 8 karakter, campur huruf dan angka.</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Ulangi password</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <button type="submit" class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark">
                    Daftar
                </button>
            </form>

            <p class="mt-6 text-small text-muted">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-brand hover:text-brand-dark">Masuk</a>
            </p>
        </div>
    </section>
</x-layouts.app>
```

`resources/views/auth/password.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="auth" class="bg-surface px-5 py-14 lg:px-10 lg:py-20">
        <div class="mx-auto w-full max-w-[420px] rounded-card border border-line bg-white p-6 shadow-card lg:p-8">
            <h1 class="font-heading text-h2 font-bold text-ink">Ganti password</h1>

            @if ($forced)
                <p role="alert" class="mt-4 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
                    Password sementara dari admin harus diganti dulu sebelum kamu bisa lanjut.
                </p>
            @endif

            @if ($errors->any())
                <p role="alert" class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                    {{ $errors->first() }}
                </p>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password saat ini</span>
                    <input type="password" name="current_password" required autocomplete="current-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Password baru</span>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                    <span class="mt-1.5 block text-caption text-muted">Minimal 8 karakter, campur huruf dan angka.</span>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-small font-semibold text-ink">Ulangi password baru</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full rounded-card border border-line px-3.5 py-2.5 text-body text-ink outline-none focus:border-brand">
                </label>

                <button type="submit" class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark">
                    Simpan password baru
                </button>
            </form>
        </div>
    </section>
</x-layouts.app>
```

- [ ] **Step 5: Write the participant dashboard view**

`resources/views/exam/dashboard.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="dashboard" class="bg-surface px-5 py-10 lg:px-10 lg:py-14">
        <div class="mx-auto max-w-page">
            <h1 class="font-heading text-h2 font-bold text-ink">Halo, {{ auth()->user()->name }}</h1>
            <p class="mt-2 text-body text-muted">
                Nomor peserta <span class="font-semibold text-ink">{{ auth()->user()->participant_number }}</span>
            </p>

            @if (session('status'))
                <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">
                    {{ session('status') }}
                </p>
            @endif

            @if ($open->isNotEmpty())
                <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Sedang dikerjakan</h2>
                <div class="mt-4 grid gap-4">
                    @foreach ($open as $attempt)
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-card border border-warning/30 bg-white p-5 shadow-card">
                            <div>
                                <strong class="block font-heading text-body font-bold text-ink">{{ $attempt->package->title }}</strong>
                                <span class="text-small text-muted">Sisa waktu {{ intdiv($attempt->remainingSeconds(), 60) }} menit</span>
                            </div>
                            <a href="{{ route('tryout.room', $attempt) }}"
                                class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                                Lanjutkan
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Paket tryout</h2>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @forelse ($packages as $package)
                    <div class="rounded-card border border-line bg-white p-5 shadow-card">
                        <strong class="block font-heading text-body font-bold text-ink">{{ $package->title }}</strong>
                        <p class="mt-1 text-small text-muted">{{ $package->description }}</p>
                        <p class="mt-3 text-caption text-muted">110 soal · {{ $package->duration_minutes }} menit</p>
                        <form method="POST" action="{{ route('tryout.start', $package) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="rounded-card bg-accent px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-accent-dark">
                                Mulai tryout
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-body text-muted">Belum ada paket yang dibuka. Cek lagi nanti ya.</p>
                @endforelse
            </div>

            @if ($finished->isNotEmpty())
                <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Riwayat</h2>
                <div class="mt-4 overflow-hidden rounded-card border border-line bg-white shadow-card">
                    <table class="w-full text-left text-small">
                        <thead class="bg-surface text-caption uppercase text-muted">
                            <tr>
                                <th class="px-4 py-3">Paket</th>
                                <th class="px-4 py-3">Skor</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($finished as $attempt)
                                <tr class="border-t border-line">
                                    <td class="px-4 py-3 text-ink">{{ $attempt->package->title }}</td>
                                    <td class="px-4 py-3 font-semibold text-ink">{{ $attempt->total_score }} / {{ config('cpns.total_max') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="{{ $attempt->hasPassed() ? 'text-success' : 'text-danger' }}">
                                            {{ $attempt->hasPassed() ? 'Lolos ambang' : 'Belum lolos' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('hasil.show', $attempt) }}" class="font-semibold text-brand hover:text-brand-dark">Analisa</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
```

- [ ] **Step 6: Add the routes**

Append to `routes/web.php` (keeping the existing `home` route on top):

```php
Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store']);
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store']);
});

Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    // Reachable while a temporary password is still in place, or the redirect
    // from `password.changed` would loop.
    Route::get('/akun/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/akun/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});
```

with the imports at the top:

```php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
```

- [ ] **Step 7: Raise the session lifetime**

In `.env` and `.env.example`, change `SESSION_LIFETIME=120` to:

```
SESSION_LIFETIME=1440
```

A two-hour session logs participants out between practice sittings for no benefit; a day matches how the product is actually used.

- [ ] **Step 8: Write the failing test**

`tests/Feature/Auth/AccountTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Peserta Uji',
            'email' => 'peserta@example.test',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];
    }

    public function test_the_landing_page_is_public(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_registering_creates_a_peserta_with_a_participant_number_and_signs_them_in(): void
    {
        $response = $this->post('/daftar', $this->payload());

        $response->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'peserta@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->isAdmin());
        $this->assertNotNull($user->participant_number);
        $this->assertStringStartsWith(now()->format('Y').'-', $user->participant_number);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_registration_rejects_a_weak_password_and_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'peserta@example.test']);

        $this->post('/daftar', $this->payload(['password' => 'pendek', 'password_confirmation' => 'pendek']))
            ->assertSessionHasErrors('password');

        $this->post('/daftar', $this->payload())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->count());
    }

    public function test_a_guest_is_sent_to_the_login_route(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_login_works_and_the_session_is_regenerated(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $response = $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_throttled_after_five_failures_for_the_same_email_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/masuk', ['email' => $user->email, 'password' => 'salah-sekali'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/masuk', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/keluar')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_a_participant_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'rahasia123',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('passwordbaru1', $user->refresh()->password));
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'bukan-ini',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('rahasia123', $user->refresh()->password));
    }

    public function test_a_temporary_password_blocks_every_page_until_it_is_changed(): void
    {
        $user = User::factory()->create([
            'password' => 'sementara1',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get('/akun/password')->assertOk();

        $this->actingAs($user)->put('/akun/password', [
            'current_password' => 'sementara1',
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user->refresh())->get('/dashboard')->assertOk();
    }
}
```

- [ ] **Step 9: Run the test to verify it fails**

Run: `php artisan test --filter=AccountTest`
Expected: FAIL — `Route [dashboard] not defined.`

- [ ] **Step 10: Run the test to verify it passes**

Run: `php artisan test --filter=AccountTest`
Expected: PASS, 10 tests. If `test_login_is_throttled...` fails, the rate limiter key must be `Str::lower($email).'|'.$request->ip()` — a mismatch between the key used for `hit()` and `tooManyAttempts()` is the usual cause.

- [ ] **Step 11: Run the whole suite and the formatter**

Run: `php artisan test && vendor/bin/pint --test`
Expected: all tests pass (the homepage tests from before this module included), and Pint reports no changes needed.

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/Auth app/Http/Controllers/DashboardController.php app/Http/Middleware resources/views/auth resources/views/exam/dashboard.blade.php routes/web.php bootstrap/app.php .env.example .env tests/Feature/Auth/AccountTest.php
git commit -m "feat(auth): hand-written session auth, roles and the forced password change"
```

### Task 9: The exam room — one engine, two faces

**Files:**
- Create: `resources/views/components/layouts/exam.blade.php`
- Create: `app/Http/Controllers/Exam/PackageController.php`, `app/Http/Controllers/Exam/AttemptController.php`
- Create: `resources/views/exam/index.blade.php`, `resources/views/exam/room.blade.php`
- Create: `resources/views/exam/partials/package-card.blade.php`, `resources/views/exam/partials/navigator.blade.php`, `resources/views/exam/partials/item.blade.php`
- Create: `resources/js/modules/exam.js`
- Create: `resources/icons/{clock,check,flag,list,arrow-left,arrow-right,skip}.svg`
- Modify: `resources/js/app.js`, `routes/web.php`, `resources/views/exam/dashboard.blade.php`
- Test: `tests/Feature/Exam/ExamRoomTest.php`

**Interfaces:**
- Consumes: `AttemptManager::startOrResume()`, `::restart()`, `::expireOverdue()`, `ExamAttempt`, `ExamPackage`.
- Produces: routes `tryout.index`, `tryout.start`, `tryout.restart`, `tryout.room`; `PackageController`, `AttemptController::show()`; the DOM contract the JS module and Task 10's endpoints rely on: `[data-exam]` (with `data-answer-url`, `data-position-url`, `data-total`, `data-answered`, `data-remaining`, `data-current-question-id`), `[data-exam-item="<id>"]`, `[data-exam-option][data-option-id][data-label][data-selected]`, `[data-exam-cell][data-question-id][data-status]`, `[data-exam-number]`, `[data-exam-clock]`, `[data-exam-status]`, `[data-exam-sheet]`, `[data-exam-sheet-toggle]`, `[data-exam-finish]`.

- [ ] **Step 1: Add the seven icons the room needs**

Each file is a real 24×24 stroke SVG in `currentColor`, matching the existing `resources/icons/arrow-up.svg` exactly in root attributes. Create them with these contents:

`resources/icons/clock.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 16 14"></polyline></svg>
```

`resources/icons/check.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
```

`resources/icons/flag.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V4"></path><path d="M5 4h12l-2.5 4L17 12H5"></path></svg>
```

`resources/icons/list.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>
```

`resources/icons/arrow-left.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="11 18 5 12 11 6"></polyline></svg>
```

`resources/icons/arrow-right.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="13 6 19 12 13 18"></polyline></svg>
```

`resources/icons/skip.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 17 18 12 13 7"></polyline><polyline points="6 17 11 12 6 7"></polyline></svg>
```

Verify each parses before moving on:

```bash
php -r 'foreach (glob("resources/icons/*.svg") as $f) { if (simplexml_load_file($f) === false) { echo "INVALID: $f\n"; } } echo "checked\n";'
```

Expected: `checked`, with no `INVALID:` lines.

- [ ] **Step 2: Write the exam layout**

`resources/views/components/layouts/exam.blade.php`:

```blade
{{--
    The exam room deliberately does NOT reuse the marketing header and footer.

    Those carry a mega menu and site-wide links, and one stray click during a
    100-minute timed exam costs the participant something that cannot be bought
    back. Same design tokens, so it still looks like the same product.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Tryout' }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-body text-body antialiased">
    {{ $slot }}
</body>
</html>
```

- [ ] **Step 3: Write the two exam controllers**

`app/Http/Controllers/Exam/PackageController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamPackage;
use App\Services\Exam\AttemptManager;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PackageController extends Controller
{
    public function index(Request $request, ContentRepository $content, AttemptManager $manager): View
    {
        // The read path closes anything that ran out of time, so the list never
        // shows a stale "still running" sitting.
        $manager->expireOverdue($request->user());

        return view('exam.index', [
            'site' => $content->site(),
            'packages' => ExamPackage::query()
                ->where('status', ExamPackage::STATUS_PUBLISHED)
                ->orderBy('title')
                ->get(),
            'openAttempts' => $request->user()->attempts()
                ->where('status', AttemptStatus::InProgress)
                ->get()
                ->keyBy('exam_package_id'),
        ]);
    }

    public function start(Request $request, ExamPackage $package, AttemptManager $manager): RedirectResponse
    {
        abort_unless($package->isPublished(), 404);

        $attempt = $manager->startOrResume($request->user(), $package);

        return redirect()->route('tryout.room', $attempt);
    }

    public function restart(Request $request, ExamPackage $package, AttemptManager $manager): RedirectResponse
    {
        abort_unless($package->isPublished(), 404);

        $attempt = $manager->restart($request->user(), $package);

        return redirect()
            ->route('tryout.room', $attempt)
            ->with('status', 'Sesi sebelumnya ditutup dan dinilai sebagai waktu habis.');
    }
}
```

`app/Http/Controllers/Exam/AttemptController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Exam\AttemptManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AttemptController extends Controller
{
    public function show(Request $request, ExamAttempt $attempt, AttemptManager $manager): View|RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        $manager->expireOverdue($request->user());
        $attempt = $attempt->fresh();

        if ($attempt->isFinished()) {
            // Task 11 points this at the results page; until it exists there is
            // nothing to read, so the participant goes back to the list.
            return redirect()->route('tryout.index');
        }

        $questions = $attempt->package->questions()->with('options')->get();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $current = $attempt->current_question_id !== null
            ? $questions->firstWhere('id', $attempt->current_question_id)
            : null;

        return view('exam.room', [
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => $answers,
            'current' => $current ?? $questions->first(),
            'answered' => $attempt->answeredCount(),
            'remaining' => $attempt->remainingSeconds(),
        ]);
    }

    /**
     * A sitting is private: another participant's URL must not open it, whichever
     * of the two ids they guess.
     */
    private function ensureOwner(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
```

- [ ] **Step 4: Write the navigator partial — rendered once, positioned twice**

`resources/views/exam/partials/navigator.blade.php`:

```blade
@props(['questions', 'answers', 'currentId'])

{{--
    One navigator, two shapes. Above 992px it is a sticky column with its own
    scroll; below it, it is a bottom sheet that slides up over the question.

    The slide is a `translate-y-full` -> `translate-y-0` pair driven by
    `data-open`, and the desktop override sets the *same* value (`translate-y-0`)
    that the open state does, so whichever of the two rules wins, the result is
    identical. That is why this does not need an `!important` or a second copy of
    the markup.

    Finish lives inside this panel on purpose: on a phone it sits behind a button,
    so nobody ends the exam at item 60 while reaching for "next".
--}}
<aside
    data-exam-sheet
    data-open="false"
    class="fixed inset-x-0 bottom-0 z-40 max-h-[70vh] translate-y-full overflow-y-auto rounded-t-card-lg border-t border-line bg-white p-4 shadow-card-hover transition-transform duration-200 lg:sticky lg:top-4 lg:z-auto lg:max-h-[calc(100vh-2rem)] lg:translate-y-0 lg:rounded-card lg:border lg:shadow-card"
>
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-heading text-body font-bold text-ink">Nomor soal</h2>
        <button type="button" data-exam-sheet-toggle class="rounded-card p-2 text-muted hover:bg-surface lg:hidden">
            <x-icon name="chevron-down" class="h-5 w-5" />
            <span class="sr-only">Tutup daftar nomor</span>
        </button>
    </div>

    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-caption text-muted">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-success"></span> sudah dijawab
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-danger"></span> belum dijawab
        </span>
        <span class="inline-flex items-center gap-1.5">
            <x-icon name="flag" class="h-3.5 w-3.5" /> ragu-ragu
        </span>
    </p>

    <div class="mt-4 grid grid-cols-5 gap-2 lg:grid-cols-6">
        @foreach ($questions as $index => $question)
            @php($answer = $answers->get($question->id))
            @php($isAnswered = $answer?->selected_option_id !== null)
            <button
                type="button"
                data-exam-cell
                data-question-id="{{ $question->id }}"
                data-status="{{ $isAnswered ? 'answered' : 'blank' }}"
                data-flag="{{ $answer?->is_flagged ? 'true' : 'false' }}"
                data-current="{{ $currentId === $question->id ? 'true' : 'false' }}"
                class="relative flex h-10 items-center justify-center rounded-card border border-danger bg-danger/5 font-heading text-small font-semibold text-danger transition data-[status=answered]:border-success data-[status=answered]:bg-success/10 data-[status=answered]:text-success data-[current=true]:ring-2 data-[current=true]:ring-brand"
            >
                {{ $index + 1 }}
                <span data-exam-cell-flag class="{{ $answer?->is_flagged ? '' : 'hidden' }} absolute top-1 right-1 text-warning">
                    <x-icon name="flag" class="h-3 w-3" />
                </span>
            </button>
        @endforeach
    </div>

    <form data-exam-finish method="POST" action="{{ route('tryout.finish', $attempt) }}" class="mt-5 lg:hidden">
        @csrf
        <button type="submit" data-exam-finish-button class="w-full rounded-card bg-brand px-5 py-3 font-heading text-body font-bold text-white transition hover:bg-brand-dark disabled:opacity-50">
            Selesai ujian
        </button>
        <p class="mt-2 text-caption text-muted">Tombol ini baru bisa dipakai kalau semua soal sudah dijawab.</p>
    </form>
</aside>
```

Note the partial uses `$attempt` for its finish form action; pass `:attempt="$attempt"` from the room. Add it to the props list: `@props(['questions', 'answers', 'currentId', 'attempt'])`.

- [ ] **Step 5: Write the item partial**

`resources/views/exam/partials/item.blade.php`:

```blade
@props(['question', 'answer', 'number', 'visible' => false])

<div
    data-exam-item="{{ $question->id }}"
    @unless ($visible) hidden @endunless
    class="rounded-card border border-line bg-white p-5 shadow-card lg:p-7"
>
    <p class="text-caption font-semibold tracking-wide text-muted uppercase">
        {{ $question->category->label() }} · {{ $question->subcategoryLabel() }}
    </p>

    <h2 class="mt-3 text-[16px] leading-[1.6] font-semibold text-ink lg:text-[17px]">
        <span class="text-muted">{{ $number }}.</span>
        {!! nl2br(e($question->stem)) !!}
    </h2>

    <div class="mt-5 space-y-2.5">
        @foreach ($question->options as $option)
            <button
                type="button"
                data-exam-option
                data-option-id="{{ $option->id }}"
                data-label="{{ $option->label }}"
                data-selected="{{ $answer?->selected_option_id === $option->id ? 'true' : 'false' }}"
                class="flex min-h-[44px] w-full items-start gap-3 rounded-card border border-line bg-white px-4 py-3 text-left text-body text-ink transition hover:border-brand data-[selected=true]:border-brand data-[selected=true]:bg-brand/5"
            >
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-line font-heading text-caption font-bold text-muted">
                    {{ $option->label }}
                </span>
                <span class="min-w-0">{{ $option->body }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-3">
        <button
            type="button"
            data-exam-flag
            data-question-id="{{ $question->id }}"
            data-flagged="{{ $answer?->is_flagged ? 'true' : 'false' }}"
            class="inline-flex items-center gap-2 rounded-card border border-line px-4 py-2.5 text-small font-semibold text-muted transition hover:border-warning hover:text-warning data-[flagged=true]:border-warning data-[flagged=true]:bg-warning/5 data-[flagged=true]:text-warning"
        >
            <x-icon name="flag" class="h-4 w-4" />
            Tandai ragu-ragu
        </button>
    </div>
</div>
```

- [ ] **Step 6: Write the room**

`resources/views/exam/room.blade.php`:

```blade
<x-layouts.exam :title="$attempt->package->title">
    <div
        data-exam
        data-current-question-id="{{ $current->id }}"
        data-answer-url="{{ route('tryout.answer', $attempt) }}"
        data-position-url="{{ route('tryout.position', $attempt) }}"
        data-total="{{ $questions->count() }}"
        data-answered="{{ $answered }}"
        data-remaining="{{ $remaining }}"
    >
        {{-- Phone: a big clock and the way to the navigator. --}}
        <header class="sticky top-0 z-30 border-b border-line bg-white px-4 py-3 lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <span data-exam-clock data-state="normal" class="font-heading text-[22px] font-bold text-ink data-[state=warning]:text-warning data-[state=danger]:text-danger">--:--</span>
                    <p class="text-caption text-muted">Soal <span data-exam-number>{{ $questions->search(fn ($question) => $question->id === $current->id) + 1 }}</span> dari {{ $questions->count() }}</p>
                </div>
                <button type="button" data-exam-sheet-toggle class="inline-flex items-center gap-2 rounded-card border border-line px-4 py-2.5 text-small font-semibold text-ink">
                    <x-icon name="list" class="h-4 w-4" />
                    Nomor soal
                </button>
            </div>
        </header>

        {{-- Desktop: the CAT-shaped strip. --}}
        <header class="hidden border-b border-line bg-white px-6 py-3 lg:block">
            <div class="mx-auto flex max-w-page flex-wrap items-center gap-x-6 gap-y-2 text-small">
                <strong class="font-heading text-body font-bold text-ink">{{ auth()->user()->name }}</strong>
                <span class="text-muted">No. peserta {{ auth()->user()->participant_number }}</span>
                <span class="text-muted">Berakhir {{ $attempt->expires_at->format('H:i') }}</span>
                <span class="text-muted">Dijawab <span data-exam-answered>{{ $answered }}</span></span>
                <span class="text-muted">Belum <span data-exam-blank>{{ $questions->count() - $answered }}</span></span>
                <span class="ml-auto inline-flex items-center gap-2">
                    <x-icon name="clock" class="h-4 w-4 text-muted" />
                    <span data-exam-clock data-state="normal" class="font-heading text-body font-bold text-ink data-[state=warning]:text-warning data-[state=danger]:text-danger">--:--</span>
                </span>
                <form data-exam-finish method="POST" action="{{ route('tryout.finish', $attempt) }}">
                    @csrf
                    <button type="submit" data-exam-finish-button @disabled($answered < $questions->count())
                        class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark disabled:opacity-40">
                        Selesai ujian
                    </button>
                </form>
            </div>
        </header>

        <div class="mx-auto grid max-w-page gap-5 px-4 py-5 lg:grid-cols-[320px_minmax(0,1fr)] lg:px-6 lg:py-6">
            @include('exam.partials.navigator', [
                'questions' => $questions,
                'answers' => $answers,
                'currentId' => $current->id,
                'attempt' => $attempt,
            ])

            <main class="min-w-0">
                @if ($errors->any())
                    <p role="alert" class="mb-4 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                        {{ $errors->first() }}
                    </p>
                @endif

                <p data-exam-status role="status" aria-live="polite" class="mb-3 h-5 text-small text-muted"></p>

                <div class="space-y-5">
                    @foreach ($questions as $index => $question)
                        @include('exam.partials.item', [
                            'question' => $question,
                            'answer' => $answers->get($question->id),
                            'number' => $index + 1,
                            'visible' => $question->id === $current->id,
                        ])
                    @endforeach
                </div>

                <div class="sticky bottom-0 mt-5 flex items-center justify-between gap-3 border-t border-line bg-surface py-3 lg:static lg:border-0">
                    <button type="button" data-exam-prev class="inline-flex items-center gap-2 rounded-card border border-line bg-white px-4 py-3 text-small font-semibold text-ink">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                        Sebelumnya
                    </button>
                    <button type="button" data-exam-skip class="inline-flex items-center gap-2 rounded-card border border-line bg-white px-4 py-3 text-small font-semibold text-muted">
                        <x-icon name="skip" class="h-4 w-4" />
                        Lewati soal ini
                    </button>
                    <button type="button" data-exam-next class="inline-flex items-center gap-2 rounded-card bg-brand px-4 py-3 text-small font-semibold text-white">
                        Berikutnya
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </button>
                </div>
            </main>
        </div>
    </div>
</x-layouts.exam>
```

`$questions->search(...)` returns a zero-based index, which is why the phone header adds 1 to
it for the item number. The clock appears twice in the markup — once in the phone bar and once
in the desktop strip — so the module queries both with `querySelectorAll` and updates the pair
together.

- [ ] **Step 7: Write the package list, the shared card, and point the dashboard at it**

`resources/views/exam/partials/package-card.blade.php`:

```blade
@props(['package', 'openAttempt' => null])

<div class="rounded-card border border-line bg-white p-5 shadow-card">
    <strong class="block font-heading text-body font-bold text-ink">{{ $package->title }}</strong>
    <p class="mt-1 text-small text-muted">{{ $package->description }}</p>
    <p class="mt-3 text-caption text-muted">
        {{ config('cpns.total_items') }} soal · {{ $package->duration_minutes }} menit · maks {{ config('cpns.total_max') }}
    </p>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        @if ($openAttempt)
            <form method="POST" action="{{ route('tryout.start', $package) }}">
                @csrf
                <button type="submit" class="rounded-card bg-accent px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-accent-dark">
                    Lanjutkan
                </button>
            </form>
            <span class="text-small text-warning">Sisa {{ intdiv($openAttempt->remainingSeconds(), 60) }} menit</span>
            <form method="POST" action="{{ route('tryout.restart', $package) }}">
                @csrf
                <button type="submit" class="rounded-card border border-line px-4 py-2.5 text-small font-semibold text-muted transition hover:border-danger hover:text-danger">
                    Mulai ulang
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('tryout.start', $package) }}">
                @csrf
                <button type="submit" class="rounded-card bg-brand px-5 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                    Mulai tryout
                </button>
            </form>
        @endif
    </div>
</div>
```

`resources/views/exam/index.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="tryout" class="bg-surface px-5 py-10 lg:px-10 lg:py-14">
        <div class="mx-auto max-w-page">
            <h1 class="font-heading text-h2 font-bold text-ink">Tryout SKD</h1>
            <p class="mt-2 text-body text-muted">
                {{ config('cpns.total_items') }} soal, {{ config('cpns.duration_minutes') }} menit, skor maksimal {{ config('cpns.total_max') }}.
                Ambang batasnya tiga-tiganya berdiri sendiri: TWK 65, TIU 80, TKP 166.
            </p>

            @if (session('status'))
                <p class="mt-5 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
                    {{ session('status') }}
                </p>
            @endif

            <div class="mt-8 grid gap-4 lg:grid-cols-2">
                @forelse ($packages as $package)
                    @include('exam.partials.package-card', [
                        'package' => $package,
                        'openAttempt' => $openAttempts->get($package->id),
                    ])
                @empty
                    <p class="text-body text-muted">Belum ada paket tryout yang dibuka.</p>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>
```

Then in `resources/views/exam/dashboard.blade.php`, replace the inlined package card block (the `<div class="rounded-card border border-line bg-white p-5 shadow-card">…</div>` inside the `@forelse ($packages as $package)`) with:

```blade
                    @include('exam.partials.package-card', ['package' => $package, 'openAttempt' => null])
```

The dashboard lists packages that are not currently open, so it passes `null`; the dedicated `/tryout` page is where an open sitting is resumed.

- [ ] **Step 8: Write the JS module**

`resources/js/modules/exam.js`:

```js
// The exam room. One module drives both layouts, because there is only one set of
// elements: the desktop sidebar and the phone bottom sheet are the same navigator
// positioned by CSS at the 992px breakpoint.
//
// State lives in data attributes — `data-selected`, `data-status`, `data-state`,
// `data-open` — so what a state looks like stays in the Blade markup and this file
// only ever flips the attribute. Classes named in this file are picked up anyway,
// because resources/js is one of Tailwind's three @source lines.
//
// Saving is optimistic: the option recolours on click and the request goes out
// behind it. If the write fails, the colour goes back and a line says so, because
// a participant on a weak connection must never be told "saved" when it was not.

const WARNING_AT = 600; // ten minutes left
const DANGER_AT = 300; // five minutes left

export function initExam() {
  const root = document.querySelector('[data-exam]');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  const answerUrl = root.dataset.answerUrl;
  const positionUrl = root.dataset.positionUrl;

  const panels = [...root.querySelectorAll('[data-exam-item]')];
  const cells = [...root.querySelectorAll('[data-exam-cell]')];
  const clocks = [...root.querySelectorAll('[data-exam-clock]')];
  const numbers = [...root.querySelectorAll('[data-exam-number]')];
  const answeredOut = [...root.querySelectorAll('[data-exam-answered]')];
  const blankOut = [...root.querySelectorAll('[data-exam-blank]')];
  const statusLine = root.querySelector('[data-exam-status]');
  const sheet = root.querySelector('[data-exam-sheet]');
  const finishForms = [...root.querySelectorAll('[data-exam-finish]')];

  const total = Number(root.dataset.total ?? panels.length);
  let answered = Number(root.dataset.answered ?? 0);
  let remaining = Number(root.dataset.remaining ?? 0);
  let index = Math.max(0, cells.findIndex((cell) => cell.dataset.questionId === root.dataset.currentQuestionId));
  let leaving = false;
  let heartbeat = null;
  let statusTimer = null;

  const currentPanel = () => panels[index];
  const currentId = () => currentPanel()?.dataset.examItem;

  const say = (message, ok = true) => {
    if (!statusLine) return;
    statusLine.textContent = message;
    statusLine.dataset.state = ok ? 'ok' : 'error';
    clearTimeout(statusTimer);
    if (ok) statusTimer = setTimeout(() => { statusLine.textContent = ''; }, 1600);
  };

  const renderCounts = () => {
    answeredOut.forEach((node) => { node.textContent = String(answered); });
    blankOut.forEach((node) => { node.textContent = String(Math.max(0, total - answered)); });

    const ready = answered >= total;
    root.querySelectorAll('[data-exam-finish-button]').forEach((button) => { button.disabled = !ready; });
  };

  const renderClock = () => {
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    const text = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    const state = remaining <= DANGER_AT ? 'danger' : remaining <= WARNING_AT ? 'warning' : 'normal';

    clocks.forEach((node) => {
      node.textContent = text;
      node.dataset.state = state;
    });
  };

  const setCellStatus = (questionId, isAnswered, isFlagged) => {
    cells
      .filter((cell) => cell.dataset.questionId === String(questionId))
      .forEach((cell) => {
        cell.dataset.status = isAnswered ? 'answered' : 'blank';
        if (typeof isFlagged === 'boolean') cell.dataset.flag = isFlagged ? 'true' : 'false';
        const flag = cell.querySelector('[data-exam-cell-flag]');
        if (flag && typeof isFlagged === 'boolean') flag.classList.toggle('hidden', !isFlagged);
      });
  };

  const markCurrentCell = () => {
    cells.forEach((cell) => {
      cell.dataset.current = cell.dataset.questionId === String(currentId()) ? 'true' : 'false';
    });
  };

  const closeSheet = () => {
    if (sheet) sheet.dataset.open = 'false';
  };

  const put = async (url, body) => {
    const response = await fetch(url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) throw Object.assign(new Error('request failed'), { data });

    return data;
  };

  const applyServerState = (data) => {
    if (typeof data.answered === 'number') answered = data.answered;
    if (typeof data.remaining_seconds === 'number') remaining = data.remaining_seconds;
    renderCounts();
    renderClock();
  };

  const onSaveFailure = (error) => {
    // The sitting ran out of time behind the participant's back: the server has
    // already graded it, so walking them to the results page is the honest move.
    if (error?.data?.attempt) {
      leave();
      window.location.reload();
      return;
    }
    say(error?.data?.message ?? 'Gagal menyimpan. Periksa koneksi lalu coba lagi.', false);
  };

  const selectOption = async (button) => {
    const panel = button.closest('[data-exam-item]');
    const questionId = Number(panel.dataset.examItem);
    const previous = panel.querySelector('[data-exam-option][data-selected="true"]');

    if (previous === button) return;

    if (previous) previous.dataset.selected = 'false';
    button.dataset.selected = 'true';
    if (!previous) {
      answered += 1;
      setCellStatus(questionId, true);
      renderCounts();
    }

    try {
      applyServerState(await put(answerUrl, { question_id: questionId, selected_option_id: Number(button.dataset.optionId) }));
      say('Tersimpan');
    } catch (error) {
      button.dataset.selected = 'false';
      if (previous) {
        previous.dataset.selected = 'true';
      } else {
        answered -= 1;
        setCellStatus(questionId, false);
        renderCounts();
      }
      onSaveFailure(error);
    }
  };

  const skipCurrent = async () => {
    const panel = currentPanel();
    if (!panel) return;

    const questionId = Number(panel.dataset.examItem);
    const previous = panel.querySelector('[data-exam-option][data-selected="true"]');

    if (previous) {
      previous.dataset.selected = 'false';
      answered -= 1;
      setCellStatus(questionId, false);
      renderCounts();
    }

    try {
      applyServerState(await put(answerUrl, { question_id: questionId, selected_option_id: null }));
      say('Dilewati');
    } catch (error) {
      onSaveFailure(error);
    }

    goTo(index + 1);
  };

  const toggleFlag = async (button) => {
    const panel = currentPanel();
    if (!panel) return;

    const questionId = Number(panel.dataset.examItem);
    const flagged = button.dataset.flagged !== 'true';
    button.dataset.flagged = flagged ? 'true' : 'false';
    setCellStatus(questionId, cellIsAnswered(questionId), flagged);

    const selected = panel.querySelector('[data-exam-option][data-selected="true"]');

    try {
      applyServerState(await put(answerUrl, {
        question_id: questionId,
        selected_option_id: selected ? Number(selected.dataset.optionId) : null,
        is_flagged: flagged,
      }));
    } catch (error) {
      button.dataset.flagged = flagged ? 'false' : 'true';
      setCellStatus(questionId, cellIsAnswered(questionId), !flagged);
      onSaveFailure(error);
    }
  };

  const cellIsAnswered = (questionId) =>
    cells.some((cell) => cell.dataset.questionId === String(questionId) && cell.dataset.status === 'answered');

  const sendPosition = () => {
    clearTimeout(heartbeat);
    heartbeat = setTimeout(() => {
      put(positionUrl, { question_id: Number(currentId()) }).catch(() => {});
    }, 800);
  };

  function goTo(next) {
    if (next < 0 || next >= panels.length) return;

    panels[index]?.setAttribute('hidden', '');
    index = next;
    panels[index]?.removeAttribute('hidden');

    numbers.forEach((node) => { node.textContent = String(index + 1); });
    markCurrentCell();
    closeSheet();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    sendPosition();
  }

  const leave = () => {
    leaving = true;
    window.removeEventListener('beforeunload', warnBeforeLeaving);
  };

  const warnBeforeLeaving = (event) => {
    if (leaving) return;
    event.preventDefault();
    event.returnValue = '';
  };

  const autoFinish = () => {
    if (leaving) return;
    leave();
    finishForms[0]?.requestSubmit();
  };

  const requestWakeLock = async () => {
    try {
      await navigator.wakeLock?.request('screen');
    } catch {
      // Unsupported, refused, or the tab is hidden. Nothing here is essential.
    }
  };

  // --- wiring -----------------------------------------------------------------

  root.addEventListener('click', (event) => {
    const option = event.target.closest('[data-exam-option]');
    if (option) return void selectOption(option);

    const flag = event.target.closest('[data-exam-flag]');
    if (flag) return void toggleFlag(flag);

    const cell = event.target.closest('[data-exam-cell]');
    if (cell) {
      const target = cells.indexOf(cell);
      if (target !== index) goTo(target);
      else closeSheet();
      return;
    }

    if (event.target.closest('[data-exam-prev]')) return void goTo(index - 1);
    if (event.target.closest('[data-exam-next]')) return void goTo(index + 1);
    if (event.target.closest('[data-exam-skip]')) return void skipCurrent();
    if (event.target.closest('[data-exam-sheet-toggle]')) {
      sheet.dataset.open = sheet.dataset.open === 'true' ? 'false' : 'true';
    }
  });

  finishForms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      const blank = total - answered;

      if (blank > 0) {
        event.preventDefault();
        say(`Masih ada ${blank} soal yang belum dijawab.`, false);
        return;
      }

      leave();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.target instanceof HTMLElement && event.target.matches('input, textarea, select')) return;

    const key = event.key.toUpperCase();

    if (['A', 'B', 'C', 'D', 'E'].includes(key)) {
      const button = currentPanel()?.querySelector(`[data-exam-option][data-label="${key}"]`);
      if (button) {
        event.preventDefault();
        selectOption(button);
      }
      return;
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      goTo(index + 1);
    }

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      goTo(index - 1);
    }
  });

  window.addEventListener('beforeunload', warnBeforeLeaving);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') requestWakeLock();
  });

  setInterval(() => {
    if (remaining <= 0) return;
    remaining -= 1;
    renderClock();
    if (remaining <= 0) autoFinish();
  }, 1000);

  renderCounts();
  renderClock();
  markCurrentCell();
  requestWakeLock();
  sendPosition();
}
```

Note the `keydown` handler reads `event.key.toUpperCase()` — for arrow keys that is `'ARROWRIGHT'`, so the two arrow checks must compare `event.key` (not `key`). They do. Keep it that way.

- [ ] **Step 9: Register the module**

Replace `resources/js/app.js` with:

```js
// Page behaviour. Each module talks to the markup through data attributes rather
// than class names, so restyling a section never breaks its behaviour.
//
// The marketing page loads all of them; the exam room only has work for
// `initExam`, and every module returns immediately when its root element is
// absent, so one bundle serves both.
import { initHeader } from './modules/header.js';
import { initProgramTabs } from './modules/program-tabs.js';
import { initCarousels } from './modules/carousel.js';
import { initBackToTop } from './modules/back-to-top.js';
import { initExam } from './modules/exam.js';
import { CAROUSEL_SETTINGS } from './modules/carousel-settings.js';

initHeader();
initProgramTabs();
initCarousels(CAROUSEL_SETTINGS);
initBackToTop();
initExam();
```

- [ ] **Step 10: Add the routes**

In `routes/web.php`, inside the `password.changed` group and below the dashboard route:

```php
        Route::get('/tryout', [PackageController::class, 'index'])->name('tryout.index');
        Route::post('/tryout/{package}/mulai', [PackageController::class, 'start'])->name('tryout.start');
        Route::post('/tryout/{package}/mulai-ulang', [PackageController::class, 'restart'])->name('tryout.restart');
        Route::get('/tryout/attempt/{attempt}', [AttemptController::class, 'show'])->name('tryout.room');
```

with the imports:

```php
use App\Http\Controllers\Exam\AttemptController;
use App\Http\Controllers\Exam\PackageController;
```

- [ ] **Step 11: Write the failing test**

`tests/Feature/Exam/ExamRoomTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExamRoomTest extends TestCase
{
    use RefreshDatabase;

    private function published(): ExamPackage
    {
        return ExamPackage::factory()->complete()->published()->create();
    }

    public function test_a_guest_is_kept_out(): void
    {
        $this->get('/tryout')->assertRedirect(route('login'));
    }

    public function test_starting_sends_the_participant_into_the_room(): void
    {
        $user = User::factory()->create();
        $package = $this->published();

        $response = $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai');

        $attempt = ExamAttempt::query()->firstOrFail();

        $response->assertRedirect(route('tryout.room', $attempt));
        $this->assertSame($user->id, $attempt->user_id);
    }

    public function test_the_room_carries_the_contract_the_script_needs(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('data-exam', escape: false);
        $this->assertMatchesRegularExpression('/data-remaining="(59\d\d|6000)"/', $response->getContent());
        $response->assertSee('data-total="110"', escape: false);
        $response->assertSee('data-current-question-id="'.$package->questions()->first()->id.'"', escape: false);
        $response->assertSee('name="csrf-token"', escape: false);

        // Every item is rendered; exactly one of them is visible.
        $this->assertSame(110, substr_count($response->getContent(), 'data-exam-item="'));
        $this->assertSame(110, substr_count($response->getContent(), 'data-exam-cell'));
        $this->assertSame(1, substr_count($response->getContent(), 'data-exam-item="'.$package->questions()->first()->id.'"'));
    }

    public function test_the_room_does_not_render_the_marketing_chrome(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id);

        $response->assertDontSee('data-section="header"', escape: false);
        $response->assertDontSee('data-section="footer"', escape: false);
    }

    public function test_another_participants_sitting_is_a_forbidden_page(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($owner, $package);

        $this->actingAs($stranger)->get('/tryout/attempt/'.$attempt->id)->assertForbidden();
    }

    public function test_an_unpublished_package_cannot_be_started(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->create();

        $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai')->assertNotFound();
        $this->assertSame(0, ExamAttempt::query()->count());
    }

    public function test_the_package_list_offers_a_resume_for_an_open_sitting(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        app(AttemptManager::class)->startOrResume($user, $package);

        $response = $this->actingAs($user)->get('/tryout');

        $response->assertOk();
        $response->assertSee('Lanjutkan');
        $response->assertSee('Mulai ulang');
    }

    public function test_restarting_closes_the_old_sitting_and_opens_a_fresh_one(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $old = app(AttemptManager::class)->startOrResume($user, $package);

        $this->actingAs($user)->post('/tryout/'.$package->slug.'/mulai-ulang');

        $this->assertSame(AttemptStatus::Expired, $old->refresh()->status);
        $this->assertSame(2, ExamAttempt::query()->count());
    }

    public function test_a_finished_sitting_leaves_the_room(): void
    {
        $user = User::factory()->create();
        $package = $this->published();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $attempt->forceFill(['status' => AttemptStatus::Submitted, 'submitted_at' => now()])->save();

        $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->assertRedirect(route('tryout.index'));
    }
}
```

- [ ] **Step 12: Run the test to verify it fails**

Run: `php artisan test --filter=ExamRoomTest`
Expected: FAIL — `Route [tryout.index] not defined.`

- [ ] **Step 13: Run the test to verify it passes**

Run: `php artisan test --filter=ExamRoomTest`
Expected: PASS, 9 tests.

- [ ] **Step 14: Build and check the room's weight in a browser**

```bash
npm run build
```

Then, with the app served locally, open a room, confirm the countdown is running, that clicking an option turns its navigator cell green, that the bottom sheet opens below 992px and is a sidebar above it, and that arrow keys and A–E work. Measure the compressed size of the room document:

```bash
curl -s -H 'Accept-Encoding: gzip' -o /dev/null -w '%{size_download}\n' 'http://127.0.0.1:8000/tryout/attempt/1'
```

Expected: the document is served (this step is a sanity check on weight — 110 rendered items are a few hundred KB uncompressed and a fraction of that gzipped; if it comes back over 1 MB uncompressed, split the items into an on-demand fetch instead of rendering them all).

- [ ] **Step 15: Commit**

```bash
git add resources/views/components/layouts/exam.blade.php app/Http/Controllers/Exam resources/views/exam resources/js/modules/exam.js resources/js/app.js resources/icons routes/web.php tests/Feature/Exam/ExamRoomTest.php
git commit -m "feat(exam): the exam room, one engine with a desktop and a phone face"
```

---

### Task 10: Answer, position and finish endpoints

**Files:**
- Create: `app/Http/Requests/Exam/StoreAnswerRequest.php`, `app/Http/Requests/Exam/StorePositionRequest.php`
- Create: `app/Http/Controllers/Exam/AnswerController.php`
- Modify: `app/Http/Controllers/Exam/AttemptController.php` (add `finish()`), `routes/web.php`
- Test: `tests/Feature/Exam/AnswerEndpointTest.php`, `tests/Feature/Exam/FinishEndpointTest.php`

**Interfaces:**
- Consumes: `AnswerRecorder::record()`, `::openQuestion()`, `AttemptManager::finish()`, the DOM contract from Task 9.
- Produces: routes `tryout.answer` (PUT, JSON), `tryout.position` (PUT, JSON), `tryout.finish` (POST, redirect); JSON body `{ok, answered, blank, remaining_seconds}`.

- [ ] **Step 1: Write the two form requests**

`app/Http/Requests/Exam/StoreAnswerRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAnswerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
            // Null is how "skip this item" travels, so it is allowed and meaningful.
            'selected_option_id' => ['nullable', 'integer'],
            'is_flagged' => ['sometimes', 'boolean'],
        ];
    }
}
```

`app/Http/Requests/Exam/StorePositionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

final class StorePositionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
        ];
    }
}
```

- [ ] **Step 2: Write the answer controller**

`app/Http/Controllers/Exam/AnswerController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreAnswerRequest;
use App\Http\Requests\Exam\StorePositionRequest;
use App\Models\ExamAttempt;
use App\Services\Exam\AnswerRecorder;
use Illuminate\Http\JsonResponse;

/**
 * The two writes the room makes. Each request names exactly one question, and the
 * response carries the freshly computed remaining seconds so a paused tab or a
 * changed system clock is pulled back into line within one click.
 */
final class AnswerController extends Controller
{
    public function update(StoreAnswerRequest $request, ExamAttempt $attempt, AnswerRecorder $recorder): JsonResponse
    {
        $this->ensureOwner($request, $attempt);

        $recorder->record(
            $attempt,
            (int) $request->validated('question_id'),
            $request->validated('selected_option_id') !== null ? (int) $request->validated('selected_option_id') : null,
            (bool) ($request->validated('is_flagged') ?? $this->existingFlag($attempt, (int) $request->validated('question_id'))),
        );

        return $this->state($attempt);
    }

    public function position(StorePositionRequest $request, ExamAttempt $attempt, AnswerRecorder $recorder): JsonResponse
    {
        $this->ensureOwner($request, $attempt);

        $question = $attempt->package->questions()
            ->whereKey((int) $request->validated('question_id'))
            ->first();

        if ($question !== null) {
            $recorder->openQuestion($attempt, $question);
        }

        return $this->state($attempt);
    }

    private function state(ExamAttempt $attempt): JsonResponse
    {
        $fresh = $attempt->refresh();
        $total = $fresh->totalItems();

        return response()->json([
            'ok' => true,
            'answered' => $fresh->answeredCount(),
            'blank' => $total - $fresh->answeredCount(),
            'remaining_seconds' => $fresh->remainingSeconds(),
        ]);
    }

    /**
     * A flag toggle and an option click use the same endpoint, so one of them
     * would erase the other's value if the flag were not carried over.
     */
    private function existingFlag(ExamAttempt $attempt, int $questionId): bool
    {
        return (bool) $attempt->answers()->where('question_id', $questionId)->value('is_flagged');
    }

    private function ensureOwner(\Illuminate\Http\Request $request, ExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
```

- [ ] **Step 3: Add `finish()` to the attempt controller**

In `app/Http/Controllers/Exam/AttemptController.php`, add the `finish` method and the two imports `use App\Services\Exam\AttemptManager;` (already there):

```php
    public function finish(Request $request, ExamAttempt $attempt, AttemptManager $manager): RedirectResponse
    {
        $this->ensureOwner($request, $attempt);

        $manager->finish($attempt);

        return redirect()->route('tryout.index');
    }
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, below the room route:

```php
        Route::put('/tryout/attempt/{attempt}/jawaban', [AnswerController::class, 'update'])->name('tryout.answer');
        Route::put('/tryout/attempt/{attempt}/posisi', [AnswerController::class, 'position'])->name('tryout.position');
        Route::post('/tryout/attempt/{attempt}/selesai', [AttemptController::class, 'finish'])->name('tryout.finish');
```

with the import `use App\Http\Controllers\Exam\AnswerController;`.

- [ ] **Step 5: Write the failing tests**

`tests/Feature/Exam/AnswerEndpointTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Models\ExamAttempt;
use App\Models\ExamAttemptAnswer;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AnswerEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: ExamAttempt, 2: Question}
     */
    private function sitting(): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        return [$user, $attempt, $package->questions()->with('options')->first()];
    }

    public function test_choosing_an_option_saves_it_and_returns_the_clock(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();

        $response = $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $option->id],
        );

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answered', 1)
            ->assertJsonPath('blank', 109);

        // The clock is the server's; a second may have passed since the sitting started.
        $this->assertGreaterThan(5900, $response->json('remaining_seconds'));
        $this->assertLessThanOrEqual(6000, $response->json('remaining_seconds'));

        $this->assertSame($option->id, $attempt->refresh()->selectedOptionFor($question));
    }

    public function test_sending_the_same_answer_twice_leaves_one_row(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();
        $url = '/tryout/attempt/'.$attempt->id.'/jawaban';

        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);
        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);

        $this->assertSame(1, ExamAttemptAnswer::query()->where('exam_attempt_id', $attempt->id)->count());
    }

    public function test_skipping_travels_as_a_null_option(): void
    {
        [$user, $attempt, $question] = $this->sitting();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => null],
        )->assertOk()->assertJsonPath('answered', 0);

        $this->assertNull($attempt->refresh()->selectedOptionFor($question));
    }

    public function test_flagging_an_item_does_not_erase_its_answer(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $option = $question->options->first();
        $url = '/tryout/attempt/'.$attempt->id.'/jawaban';

        $this->actingAs($user)->putJson($url, ['question_id' => $question->id, 'selected_option_id' => $option->id]);
        $this->actingAs($user)->putJson($url, [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
            'is_flagged' => true,
        ]);
        $this->actingAs($user)->putJson($url, [
            'question_id' => $question->id,
            'selected_option_id' => $option->id,
        ]);

        $answer = $attempt->refresh()->answers()->where('question_id', $question->id)->firstOrFail();

        $this->assertTrue($answer->is_flagged);
        $this->assertSame($option->id, $answer->selected_option_id);
    }

    public function test_moving_on_records_where_the_participant_is(): void
    {
        [$user, $attempt, $question] = $this->sitting();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/posisi',
            ['question_id' => $question->id],
        )->assertOk();

        $this->assertSame($question->id, $attempt->refresh()->current_question_id);
    }

    public function test_a_question_outside_the_package_is_refused(): void
    {
        [$user, $attempt] = $this->sitting();
        $stranger = Question::factory()->twk()->create();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $stranger->id, 'selected_option_id' => $stranger->options->first()->id],
        )->assertStatus(422);
    }

    public function test_a_late_answer_is_refused_and_the_sitting_is_closed(): void
    {
        [$user, $attempt, $question] = $this->sitting();
        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($user)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertStatus(422);

        $this->assertSame(\App\Enums\AttemptStatus::Expired, $attempt->refresh()->status);
    }

    public function test_another_participants_attempt_is_forbidden(): void
    {
        [, $attempt, $question] = $this->sitting();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->putJson(
            '/tryout/attempt/'.$attempt->id.'/jawaban',
            ['question_id' => $question->id, 'selected_option_id' => $question->options->first()->id],
        )->assertForbidden();
    }
}
```

`tests/Feature/Exam/FinishEndpointTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class FinishEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    public function test_finishing_with_a_blank_item_is_refused_by_the_server(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $blank = $attempt->answers()->whereNotNull('selected_option_id')->first();
        $blank->forceFill(['selected_option_id' => null])->save();

        $this->actingAs($user)
            ->from('/tryout/attempt/'.$attempt->id)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect('/tryout/attempt/'.$attempt->id)
            ->assertSessionHasErrors('attempt');

        $this->assertSame(AttemptStatus::InProgress, $attempt->refresh()->status);
    }

    public function test_finishing_a_full_paper_grades_it_and_leaves_the_room(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        $this->actingAs($user)
            ->post('/tryout/attempt/'.$attempt->id.'/selesai')
            ->assertRedirect(route('tryout.index'));

        $graded = $attempt->refresh();

        $this->assertSame(AttemptStatus::Submitted, $graded->status);
        $this->assertSame(550, $graded->total_score);
    }
}
```

- [ ] **Step 6: Run the tests to verify they fail**

Run: `php artisan test --filter='AnswerEndpointTest|FinishEndpointTest'`
Expected: FAIL — `Route [tryout.answer] not defined.`

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter='AnswerEndpointTest|FinishEndpointTest'`
Expected: PASS, 10 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Exam app/Http/Controllers/Exam/AnswerController.php app/Http/Controllers/Exam/AttemptController.php routes/web.php tests/Feature/Exam/AnswerEndpointTest.php tests/Feature/Exam/FinishEndpointTest.php
git commit -m "feat(exam): answer, position and finish endpoints"
```

### Task 11: Score analysis — the read model and the results page

**Files:**
- Create: `app/Services/Exam/ScoreReport.php`
- Create: `app/Http/Controllers/Exam/ResultController.php`
- Create: `resources/views/exam/result.blade.php`, `resources/views/exam/partials/result-{scores,subtopics,timing,history,review}.blade.php`
- Modify: `app/Http/Controllers/Exam/AttemptController.php` (both redirects now point at the results page), `resources/views/exam/dashboard.blade.php` (link to results), `routes/web.php`
- Modify (tests): `tests/Feature/Exam/ExamRoomTest.php`, `tests/Feature/Exam/FinishEndpointTest.php`
- Test: `tests/Feature/Exam/ResultPageTest.php`

**Interfaces:**
- Consumes: `ExamAttempt` (frozen scores, answers, subtopic rollups), `config('cpns.*')`.
- Produces: `ScoreReport::total()`, `::maximum()`, `::passed()`, `::subtests(): array`, `::subtopics(): Collection`, `::timing(): array`, `::history(): array`, `::chart(): array`, `::review(string $filter = 'all', ?QuestionCategory $category = null): Collection`; route `hasil.show`.

- [ ] **Step 1: Write the read model**

`app/Services/Exam/ScoreReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Enums\AttemptStatus;
use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptSubtopic;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Everything the results page shows, read from what was frozen at submit. No page
 * view ever re-scores 110 items: the grader wrote the answer rows and the subtopic
 * rollup once, and this class only aggregates them.
 *
 * An unsubmitted attempt has no stored score, so a report built on one is
 * meaningless by construction — the controller refuses to build it.
 */
final class ScoreReport
{
    public function __construct(private readonly ExamAttempt $attempt) {}

    public function total(): int
    {
        return (int) $this->attempt->total_score;
    }

    public function maximum(): int
    {
        return (int) config('cpns.total_max');
    }

    public function passed(): bool
    {
        return $this->attempt->hasPassed();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function subtests(): array
    {
        $rollup = $this->attempt->subtopics->groupBy(fn (ExamAttemptSubtopic $row): string => $row->category->value);

        $subtests = [];

        foreach (QuestionCategory::cases() as $category) {
            $rows = $rollup->get($category->value, new Collection);
            $score = $this->attempt->scoreFor($category);
            $max = $category->maxScore();
            $threshold = $category->threshold();

            $subtests[$category->value] = [
                'key' => $category->value,
                'label' => $category->label(),
                'name' => $category->name(),
                'score' => $score,
                'max' => $max,
                'threshold' => $threshold,
                'passed' => $score >= $threshold,
                'shortfall' => max(0, $threshold - $score),
                'correct' => (int) $rows->sum('correct'),
                'wrong' => (int) $rows->sum('wrong'),
                'blank' => (int) $rows->sum('blank'),
                'percent' => round($score / $max * 100, 1),
                'threshold_percent' => round($threshold / $max * 100, 1),
                'has_key' => $category->requiresKey(),
                // TKP has no wrong answers, so its average option weight is what
                // shows whether the participant picks defensible options.
                'average_weight' => $category === QuestionCategory::Tkp ? $this->averageTkpWeight() : null,
            ];
        }

        return $subtests;
    }

    public function averageTkpWeight(): float
    {
        $weights = $this->attempt->answers()
            ->whereNotNull('selected_option_id')
            ->whereHas('question', fn ($query) => $query->where('category', QuestionCategory::Tkp->value))
            ->pluck('score_awarded');

        return $weights->isEmpty() ? 0.0 : round((float) $weights->avg(), 2);
    }

    /**
     * Weakest first, because this is the section that answers "what do I study".
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function subtopics(): Collection
    {
        return $this->attempt->subtopics()
            ->get()
            ->map(function (ExamAttemptSubtopic $row): array {
                $total = $row->correct + $row->wrong + $row->blank;

                return [
                    'label' => $row->label(),
                    'category' => $row->category->label(),
                    'correct' => $row->correct,
                    'wrong' => $row->wrong,
                    'blank' => $row->blank,
                    'total' => $total,
                    'score' => $row->score,
                    'ratio' => $total === 0 ? 0.0 : round($row->correct / $total * 100, 1),
                ];
            })
            ->sortBy('ratio')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function timing(): array
    {
        $answers = $this->attempt->answers()->with('question.options')->get();
        $budget = (int) $this->attempt->package->duration_minutes * 60;

        $endedAt = $this->attempt->submitted_at ?? now();
        $used = min($budget, max(0, $endedAt->getTimestamp() - $this->attempt->started_at->getTimestamp()));

        $slowest = $answers
            ->sortByDesc('seconds_spent')
            ->take(5)
            ->map(fn ($answer): array => [
                'label' => $answer->question->subcategoryLabel(),
                'stem' => $answer->question->stem,
                'seconds' => $answer->seconds_spent,
            ])
            ->values();

        // Answered fast and wrong is near-certainly a guess. TKP items are never
        // wrong, so they cannot appear here; the UI says so.
        $guesses = $answers
            ->filter(fn ($answer): bool => $answer->is_correct === false
                && $answer->selected_option_id !== null
                && $answer->seconds_spent < (int) config('cpns.guess_threshold_seconds'))
            ->count();

        return [
            'used_seconds' => $used,
            'budget_seconds' => $budget,
            'used_percent' => $budget === 0 ? 0.0 : round($used / $budget * 100, 1),
            'average_seconds' => $answers->isEmpty() ? 0 : (int) round($answers->avg('seconds_spent')),
            'slowest' => $slowest,
            'guesses' => $guesses,
            'guess_threshold' => (int) config('cpns.guess_threshold_seconds'),
            'is_estimate' => true,
        ];
    }

    /**
     * Every sitting of this participant on this package, oldest first, so the chart
     * and the table tell the story of their own progress and nobody else's.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        $attempts = ExamAttempt::query()
            ->where('user_id', $this->attempt->user_id)
            ->where('exam_package_id', $this->attempt->exam_package_id)
            ->whereIn('status', [AttemptStatus::Submitted->value, AttemptStatus::Expired->value])
            ->orderBy('submitted_at')
            ->get();

        $rows = [];
        $previous = null;

        foreach ($attempts as $attempt) {
            $rows[] = [
                'id' => $attempt->id,
                'at' => $attempt->submitted_at,
                'total' => (int) $attempt->total_score,
                'twk' => (int) $attempt->twk_score,
                'tiu' => (int) $attempt->tiu_score,
                'tkp' => (int) $attempt->tkp_score,
                'status' => $attempt->status,
                'delta' => $previous === null ? null : (int) $attempt->total_score - $previous,
                'is_current' => $attempt->id === $this->attempt->id,
            ];

            $previous = (int) $attempt->total_score;
        }

        return $rows;
    }

    /**
     * Chart geometry for a server-rendered SVG. The project has no chart library
     * and this is a handful of points and lines, so 200KB of JavaScript would not
     * be proportionate.
     *
     * @return array<string, mixed>
     */
    public function chart(): array
    {
        $history = $this->history();
        $width = 640;
        $height = 200;
        $padding = 28;
        $scale = fn (int $value): float => $height - $padding - ($value / max(1, $this->maximum())) * ($height - 2 * $padding);
        $count = count($history);
        $step = $count > 1 ? ($width - 2 * $padding) / ($count - 1) : 0;

        $definitions = [
            ['key' => 'total', 'label' => 'Total', 'color' => '#0657a8', 'threshold' => null],
            ['key' => 'twk', 'label' => 'TWK', 'color' => '#15803d', 'threshold' => QuestionCategory::Twk->threshold()],
            ['key' => 'tiu', 'label' => 'TIU', 'color' => '#b45309', 'threshold' => QuestionCategory::Tiu->threshold()],
            ['key' => 'tkp', 'label' => 'TKP', 'color' => '#c2410c', 'threshold' => QuestionCategory::Tkp->threshold()],
        ];

        $series = [];

        foreach ($definitions as $definition) {
            $points = [];

            foreach ($history as $index => $row) {
                $points[] = round($padding + $index * $step, 1).','.round($scale((int) $row[$definition['key']]), 1);
            }

            $series[] = $definition + [
                'points' => implode(' ', $points),
                'threshold_y' => $definition['threshold'] === null ? null : round($scale($definition['threshold']), 1),
            ];
        }

        return [
            'width' => $width,
            'height' => $height,
            'padding' => $padding,
            'series' => $series,
            'count' => $count,
        ];
    }

    /**
     * All 110 items with the participant's answer, the key and the explanation,
     * filterable to all / wrong / blank / correct. This is where the learning
     * actually happens, and it is why explanations open at submit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function review(string $filter = 'all', ?QuestionCategory $category = null): Collection
    {
        $questions = $this->attempt->package->questions()->with('options')->get();
        $answers = $this->attempt->answers()->get()->keyBy('question_id');

        $rows = $questions->values()->map(function (Question $question, int $index) use ($answers): array {
            $answer = $answers->get($question->id);
            $selected = $answer?->selected_option_id;

            $status = match (true) {
                $selected === null => 'blank',
                $question->category->requiresKey() => $answer->is_correct ? 'correct' : 'wrong',
                default => 'correct',
            };

            return [
                'number' => $index + 1,
                'question' => $question,
                'answer' => $answer,
                'status' => $status,
            ];
        });

        return $rows
            ->when($category !== null, fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $row['question']->category === $category,
            ))
            ->when($filter !== 'all', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $row['status'] === $filter,
            ))
            ->values();
    }
}
```

- [ ] **Step 2: Write the results controller**

`app/Http/Controllers/Exam/ResultController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\Exam\ScoreReport;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ResultController extends Controller
{
    private const FILTERS = ['all', 'wrong', 'blank', 'correct'];

    public function show(Request $request, ExamAttempt $attempt, ContentRepository $content): View|RedirectResponse
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);

        // No route may reveal a score before the sitting is submitted.
        if ($attempt->status->isOpen()) {
            return redirect()->route('tryout.room', $attempt);
        }

        $filter = $request->query('filter');
        $filter = is_string($filter) && in_array($filter, self::FILTERS, true) ? $filter : 'all';

        $report = new ScoreReport($attempt);

        return view('exam.result', [
            'site' => $content->site(),
            'attempt' => $attempt,
            'report' => $report,
            'filter' => $filter,
            'review' => $report->review($filter),
            'subtests' => $report->subtests(),
            'subtopics' => $report->subtopics(),
            'timing' => $report->timing(),
            'history' => $report->history(),
            'chart' => $report->chart(),
        ]);
    }
}
```

- [ ] **Step 3: Write the results view and its five partials**

`resources/views/exam/result.blade.php`:

```blade
<x-layouts.app :site="$site" :footer="[]" :floating="[]">
    <section data-section="result" class="bg-surface px-5 py-10 lg:px-10 lg:py-14">
        <div class="mx-auto max-w-page space-y-6">
            <header>
                <h1 class="font-heading text-h2 font-bold text-ink">Analisa skor</h1>
                <p class="mt-2 text-body text-muted">
                    {{ $attempt->package->title }} ·
                    {{ $attempt->status === \App\Enums\AttemptStatus::Expired ? 'waktu habis' : 'selesai' }} ·
                    {{ $attempt->submitted_at?->translatedFormat('d F Y, H:i') }}
                </p>
            </header>

            @include('exam.partials.result-scores', ['report' => $report, 'subtests' => $subtests])
            @include('exam.partials.result-subtopics', ['subtopics' => $subtopics])
            @include('exam.partials.result-timing', ['timing' => $timing])
            @include('exam.partials.result-history', ['history' => $history, 'chart' => $chart, 'report' => $report])
            @include('exam.partials.result-review', ['review' => $review, 'filter' => $filter, 'attempt' => $attempt])

            <div class="flex flex-wrap gap-3 pt-2">
                <a href="{{ route('tryout.index') }}" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
                    Kembali ke daftar tryout
                </a>
                <a href="{{ route('dashboard') }}" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
                    Dashboard
                </a>
            </div>
        </div>
    </section>
</x-layouts.app>
```

`resources/views/exam/partials/result-scores.blade.php`:

```blade
@props(['report', 'subtests'])

<div class="grid gap-4 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
    <div class="rounded-card border border-line bg-white p-6 text-center shadow-card">
        <p class="text-caption font-semibold tracking-wide text-muted uppercase">Skor total</p>
        <p class="mt-2 font-heading text-[48px] leading-none font-bold text-ink">{{ $report->total() }}</p>
        <p class="mt-1 text-small text-muted">dari {{ $report->maximum() }}</p>
        <p class="mt-4 inline-block rounded-card px-4 py-2 font-heading text-small font-bold {{ $report->passed() ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
            {{ $report->passed() ? 'Lolos ambang batas' : 'Belum lolos ambang batas' }}
        </p>
        <p class="mt-4 text-caption text-muted">
            Ambang batasnya berdiri sendiri per subtes, bukan dijumlah. Skor total yang tinggi
            masih bisa gugur kalau satu subtes di bawah garisnya.
        </p>
    </div>

    <div class="grid gap-4">
        @foreach ($subtests as $subtest)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <strong class="font-heading text-body font-bold text-ink">
                        {{ $subtest['label'] }}
                        <span class="font-body text-caption font-normal text-muted">{{ $subtest['name'] }}</span>
                    </strong>
                    <span class="font-heading text-body font-bold {{ $subtest['passed'] ? 'text-success' : 'text-danger' }}">
                        {{ $subtest['score'] }} / {{ $subtest['max'] }}
                    </span>
                </div>

                {{-- The tick sits exactly on the threshold, so "how far short" is visible without arithmetic. --}}
                <div class="relative mt-4 h-3 rounded-full bg-surface">
                    <div class="h-3 rounded-full {{ $subtest['passed'] ? 'bg-success' : 'bg-danger' }}" style="width: {{ min(100, $subtest['percent']) }}%"></div>
                    <span class="absolute -top-1 h-5 w-0.5 bg-ink" style="left: {{ $subtest['threshold_percent'] }}%" title="Ambang batas {{ $subtest['threshold'] }}"></span>
                </div>

                <p class="mt-2 text-caption text-muted">
                    Ambang {{ $subtest['threshold'] }}
                    ({{ $subtest['passed'] ? 'lolos' : 'kurang '.$subtest['shortfall'].' poin' }})
                </p>

                <dl class="mt-4 grid grid-cols-3 gap-3 text-small">
                    @if ($subtest['has_key'])
                        <div><dt class="text-muted">Benar</dt><dd class="font-semibold text-success">{{ $subtest['correct'] }}</dd></div>
                        <div><dt class="text-muted">Salah</dt><dd class="font-semibold text-danger">{{ $subtest['wrong'] }}</dd></div>
                    @else
                        <div><dt class="text-muted">Terjawab</dt><dd class="font-semibold text-ink">{{ $subtest['correct'] }}</dd></div>
                        <div><dt class="text-muted">Rata-rata bobot</dt><dd class="font-semibold text-ink">{{ $subtest['average_weight'] }}</dd></div>
                    @endif
                    <div><dt class="text-muted">Kosong</dt><dd class="font-semibold text-muted">{{ $subtest['blank'] }}</dd></div>
                </dl>

                @unless ($subtest['has_key'])
                    <p class="mt-3 text-caption text-muted">
                        TKP tidak punya jawaban salah: yang dinilai kesesuaiannya dengan nilai ASN, jadi
                        opsi terlemah pun bernilai 1 dan mengosongkan bernilai 0.
                    </p>
                @endunless
            </div>
        @endforeach
    </div>
</div>
```

`resources/views/exam/partials/result-subtopics.blade.php`:

```blade
@props(['subtopics'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Per submateri</h2>
    <p class="mt-1 text-small text-muted">Diurutkan dari yang paling lemah, jadi yang paling perlu dibaca ada di atas.</p>

    <div class="mt-5 space-y-3">
        @forelse ($subtopics as $row)
            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 text-small">
                    <span class="font-semibold text-ink">{{ $row['label'] }} <span class="text-caption font-normal text-muted">{{ $row['category'] }}</span></span>
                    <span class="text-muted">
                        benar {{ $row['correct'] }} · salah {{ $row['wrong'] }} · kosong {{ $row['blank'] }} · {{ $row['score'] }} poin
                    </span>
                </div>
                <div class="mt-2 h-2 rounded-full bg-surface">
                    <div class="h-2 rounded-full {{ $row['ratio'] >= 70 ? 'bg-success' : ($row['ratio'] >= 40 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ min(100, $row['ratio']) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-small text-muted">Belum ada rincian submateri untuk percobaan ini.</p>
        @endforelse
    </div>
</div>
```

`resources/views/exam/partials/result-timing.blade.php`:

```blade
@props(['timing'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Waktu</h2>

    <dl class="mt-4 grid grid-cols-2 gap-4 text-small lg:grid-cols-4">
        <div>
            <dt class="text-muted">Terpakai</dt>
            <dd class="font-heading text-body font-bold text-ink">
                {{ intdiv($timing['used_seconds'], 60) }} dari {{ intdiv($timing['budget_seconds'], 60) }} menit
            </dd>
        </div>
        <div>
            <dt class="text-muted">Rata-rata per soal</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['average_seconds'] }} detik</dd>
        </div>
        <div>
            <dt class="text-muted">Dijawab &lt; {{ $timing['guess_threshold'] }} detik lalu salah</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['guesses'] }} soal</dd>
        </div>
        <div>
            <dt class="text-muted">Porsi waktu</dt>
            <dd class="font-heading text-body font-bold text-ink">{{ $timing['used_percent'] }}%</dd>
        </div>
    </dl>

    <h3 class="mt-6 font-heading text-body font-bold text-ink">Lima soal paling lama</h3>
    <ol class="mt-3 space-y-2 text-small">
        @forelse ($timing['slowest'] as $index => $row)
            <li class="flex items-baseline gap-3">
                <span class="text-muted">{{ $index + 1 }}.</span>
                <span class="min-w-0 flex-1 truncate text-ink">{{ $row['stem'] }}</span>
                <span class="shrink-0 font-semibold text-ink">{{ $row['seconds'] }} detik</span>
            </li>
        @empty
            <li class="text-muted">Belum ada data waktu.</li>
        @endforelse
    </ol>

    <p class="mt-4 text-caption text-muted">
        Angka waktu adalah perkiraan dari interval antar perpindahan soal, bukan stopwatch, dan satu soal
        paling banyak dihitung {{ intdiv((int) config('cpns.timing_cap_seconds'), 60) }} menit.
        Soal TKP tidak pernah muncul di daftar tebakan karena TKP tidak punya jawaban salah.
    </p>
</div>
```

`resources/views/exam/partials/result-history.blade.php`:

```blade
@props(['history', 'chart', 'report'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <h2 class="font-heading text-h3 font-bold text-ink">Perkembangan</h2>
    <p class="mt-1 text-small text-muted">Dibandingkan dengan percobaanmu sendiri di paket yang sama.</p>

    @if ($chart['count'] > 1)
        <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="mt-4 h-auto w-full" role="img" aria-label="Grafik perkembangan skor">
            @foreach ($chart['series'] as $serie)
                @if ($serie['threshold_y'] !== null)
                    <line x1="{{ $chart['padding'] }}" y1="{{ $serie['threshold_y'] }}" x2="{{ $chart['width'] - $chart['padding'] }}" y2="{{ $serie['threshold_y'] }}"
                        stroke="{{ $serie['color'] }}" stroke-width="1" stroke-dasharray="4 4" opacity="0.4"></line>
                @endif
                <polyline points="{{ $serie['points'] }}" fill="none" stroke="{{ $serie['color'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
            @endforeach
        </svg>

        <ul class="mt-3 flex flex-wrap gap-4 text-caption text-muted">
            @foreach ($chart['series'] as $serie)
                <li class="inline-flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $serie['color'] }}"></span>
                    {{ $serie['label'] }}
                </li>
            @endforeach
            <li class="inline-flex items-center gap-2">garis putus-putus = ambang batas subtes</li>
        </ul>
    @else
        <p class="mt-4 text-small text-muted">Grafik muncul setelah kamu mengerjakan paket ini lebih dari sekali.</p>
    @endif

    <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[520px] text-left text-small">
            <thead class="bg-surface text-caption uppercase text-muted">
                <tr>
                    <th class="px-3 py-2">Tanggal</th>
                    <th class="px-3 py-2">TWK</th>
                    <th class="px-3 py-2">TIU</th>
                    <th class="px-3 py-2">TKP</th>
                    <th class="px-3 py-2">Total</th>
                    <th class="px-3 py-2">Selisih</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $row)
                    <tr class="border-t border-line {{ $row['is_current'] ? 'bg-brand/5' : '' }}">
                        <td class="px-3 py-2 text-ink">{{ $row['at']?->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-3 py-2">{{ $row['twk'] }}</td>
                        <td class="px-3 py-2">{{ $row['tiu'] }}</td>
                        <td class="px-3 py-2">{{ $row['tkp'] }}</td>
                        <td class="px-3 py-2 font-semibold text-ink">{{ $row['total'] }} / {{ $report->maximum() }}</td>
                        <td class="px-3 py-2">
                            @if ($row['delta'] === null)
                                <span class="text-muted">—</span>
                            @else
                                <span class="{{ $row['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $row['delta'] >= 0 ? '+' : '' }}{{ $row['delta'] }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if (! $row['is_current'])
                                <a href="{{ route('hasil.show', $row['id']) }}" class="font-semibold text-brand hover:text-brand-dark">Buka</a>
                            @else
                                <span class="text-caption text-muted">yang ini</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
```

`resources/views/exam/partials/result-review.blade.php`:

```blade
@props(['review', 'filter', 'attempt'])

<div class="rounded-card border border-line bg-white p-5 shadow-card lg:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-heading text-h3 font-bold text-ink">Pembahasan</h2>
        <nav class="flex flex-wrap gap-2">
            @foreach (['all' => 'Semua', 'wrong' => 'Salah', 'blank' => 'Kosong', 'correct' => 'Benar'] as $key => $label)
                <a href="{{ route('hasil.show', ['attempt' => $attempt, 'filter' => $key]) }}"
                    class="rounded-card border px-3.5 py-2 text-small font-semibold transition {{ $filter === $key ? 'border-brand bg-brand/5 text-brand' : 'border-line text-muted hover:border-brand hover:text-brand' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="mt-5 space-y-6">
        @forelse ($review as $row)
            @php($question = $row['question'])
            @php($answer = $row['answer'])
            <article class="border-t border-line pt-5 first:border-0 first:pt-0">
                <p class="text-caption font-semibold tracking-wide text-muted uppercase">
                    Soal {{ $row['number'] }} · {{ $question->category->label() }} · {{ $question->subcategoryLabel() }}
                    <span class="{{ match ($row['status']) {
                        'correct' => 'text-success',
                        'wrong' => 'text-danger',
                        default => 'text-muted',
                    } }}">
                        {{ match ($row['status']) { 'correct' => 'benar', 'wrong' => 'salah', default => 'kosong' } }}
                    </span>
                </p>

                <h3 class="mt-2 font-semibold text-ink">{!! nl2br(e($question->stem)) !!}</h3>

                <ul class="mt-3 space-y-2">
                    @foreach ($question->options as $option)
                        @php($isSelected = $answer?->selected_option_id === $option->id)
                        @php($isKey = $option->is_correct === true)
                        <li class="flex items-start gap-3 rounded-card border px-4 py-2.5 text-small
                            {{ $isKey ? 'border-success bg-success/5' : ($isSelected ? 'border-danger bg-danger/5' : 'border-line') }}">
                            <span class="font-heading font-bold text-muted">{{ $option->label }}</span>
                            <span class="min-w-0 flex-1 text-ink">{{ $option->body }}</span>
                            @if ($option->score !== null)
                                <span class="shrink-0 text-caption text-muted">bobot {{ $option->score }}</span>
                            @endif
                            @if ($isSelected)
                                <span class="shrink-0 text-caption font-semibold {{ $isKey ? 'text-success' : 'text-danger' }}">jawabanmu</span>
                            @elseif ($isKey)
                                <span class="shrink-0 text-caption font-semibold text-success">kunci</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($question->explanation)
                    <p class="mt-3 rounded-card bg-surface px-4 py-3 text-small text-body">
                        <strong class="font-semibold text-ink">Pembahasan:</strong> {{ $question->explanation }}
                    </p>
                @endif
            </article>
        @empty
            <p class="text-small text-muted">Tidak ada soal yang cocok dengan filter ini.</p>
        @endforelse
    </div>
</div>
```

- [ ] **Step 4: Point both redirects at the results page**

In `app/Http/Controllers/Exam/AttemptController.php`, change the two `redirect()->route('tryout.index')` calls to `redirect()->route('hasil.show', $attempt)` — one is the finished-sitting branch of `show()`, the other is the end of `finish()`.

In `resources/views/exam/dashboard.blade.php`, the history table's "Analisa" link already targets `route('hasil.show', $attempt)`; leave it.

- [ ] **Step 5: Add the route**

In `routes/web.php`, below the finish route:

```php
        Route::get('/hasil/{attempt}', [ResultController::class, 'show'])->name('hasil.show');
```

with the import `use App\Http\Controllers\Exam\ResultController;`.

- [ ] **Step 6: Update the two tests that expected the old destination**

In `tests/Feature/Exam/ExamRoomTest.php`, change:

```php
        $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->assertRedirect(route('tryout.index'));
```

to:

```php
        $this->actingAs($user)->get('/tryout/attempt/'.$attempt->id)->assertRedirect(route('hasil.show', $attempt));
```

In `tests/Feature/Exam/FinishEndpointTest.php`, change the assertion `->assertRedirect(route('tryout.index'))` to `->assertRedirect(route('hasil.show', $attempt))`.

- [ ] **Step 7: Write the failing test**

`tests/Feature/Exam/ResultPageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\QuestionCategory;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use App\Services\Exam\AttemptManager;
use App\Services\Exam\ExamGrader;
use App\Services\Exam\ScoreReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ResultPageTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    /**
     * @return array{0: User, 1: ExamAttempt}
     */
    private function gradedPaper(string $strategy = 'best'): array
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, $strategy);

        return [$user, app(ExamGrader::class)->grade($attempt)];
    }

    public function test_an_unsubmitted_sitting_shows_no_numbers(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertRedirect(route('tryout.room', $attempt));
    }

    public function test_another_participants_result_is_forbidden(): void
    {
        [, $attempt] = $this->gradedPaper();

        $this->actingAs(User::factory()->create())
            ->get('/hasil/'.$attempt->id)
            ->assertForbidden();
    }

    public function test_the_result_page_shows_the_total_the_thresholds_and_the_explanations(): void
    {
        [$user, $attempt] = $this->gradedPaper();

        $response = $this->actingAs($user)->get('/hasil/'.$attempt->id);

        $response->assertOk();
        $response->assertSee('550');
        $response->assertSee('Lolos ambang batas');
        $response->assertSee('TWK');
        $response->assertSee('TIU');
        $response->assertSee('TKP');
        $response->assertSee('Pembahasan');
        // The threshold tick is a real element, not a picture.
        $response->assertSee('Ambang batas', escape: false);
    }

    public function test_the_report_reads_the_frozen_scores(): void
    {
        [, $attempt] = $this->gradedPaper();
        $report = new ScoreReport($attempt);

        $this->assertSame(550, $report->total());
        $this->assertSame(550, $report->maximum());
        $this->assertTrue($report->passed());

        $subtests = $report->subtests();

        $this->assertSame(150, $subtests['twk']['score']);
        $this->assertSame(65, $subtests['twk']['threshold']);
        $this->assertSame(0, $subtests['twk']['blank']);
        $this->assertFalse($subtests['twk']['has_key'] === false);
        $this->assertSame(5.0, $subtests['tkp']['average_weight']);
        $this->assertNull($subtests['twk']['average_weight']);
    }

    public function test_editing_a_question_afterwards_does_not_move_a_finished_score(): void
    {
        [, $attempt] = $this->gradedPaper();
        $question = $attempt->package->questions()->first();

        $question->options()->update(['is_correct' => false]);
        $question->forceFill(['stem' => 'Teks soal yang diperbaiki setelah ujian'])->save();

        $report = new ScoreReport($attempt->refresh());

        $this->assertSame(550, $report->total());
        // The text does reach past participants, which is the point of reading it live.
        $this->assertStringContainsString('diperbaiki setelah ujian', $report->review()->first()['question']->stem);
    }

    public function test_the_review_filter_narrows_to_one_status(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $attempt = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($attempt, 'best');

        // Exactly one TWK item is pointed at a wrong option before the paper is
        // graded, so "wrong" has a known size instead of whatever the fixture did.
        $twk = $package->questions()->category(QuestionCategory::Twk)->first();
        $wrongOption = $twk->options->firstWhere('is_correct', false);

        $attempt->answers()
            ->where('question_id', $twk->id)
            ->update(['selected_option_id' => $wrongOption->id]);

        $graded = app(ExamGrader::class)->grade($attempt->refresh());
        $report = new ScoreReport($graded);

        $this->assertSame(1, $report->review('wrong')->count());
        // 45 TKP items count as answered rather than correct, 29 TWK and 35 TIU.
        $this->assertSame(109, $report->review('correct')->count());

        $this->actingAs($user)
            ->get('/hasil/'.$graded->id.'?filter=wrong')
            ->assertOk()
            ->assertSee('salah');
    }

    public function test_the_subtopic_breakdown_is_ordered_weakest_first(): void
    {
        [, $attempt] = $this->gradedPaper();
        $report = new ScoreReport($attempt);

        $ratios = $report->subtopics()->pluck('ratio')->all();
        $sorted = $ratios;
        sort($sorted);

        $this->assertSame($sorted, $ratios);
        $this->assertNotEmpty($ratios);
    }

    public function test_the_timing_block_is_labelled_as_an_estimate(): void
    {
        [, $attempt] = $this->gradedPaper();
        $timing = (new ScoreReport($attempt))->timing();

        $this->assertTrue($timing['is_estimate']);
        $this->assertSame(6000, $timing['budget_seconds']);
        $this->assertCount(5, $timing['slowest']);
    }

    public function test_a_second_sitting_adds_a_history_row_and_a_delta(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();

        $first = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($first, 'best');
        app(ExamGrader::class)->grade($first);

        $second = app(AttemptManager::class)->startOrResume($user, $package);
        $this->answerEveryQuestion($second, 'blank');
        $graded = app(ExamGrader::class)->grade($second);

        $history = (new ScoreReport($graded))->history();

        $this->assertCount(2, $history);
        $this->assertNull($history[0]['delta']);
        $this->assertSame(-550, $history[1]['delta']);
        $this->assertTrue($history[1]['is_current']);
        $this->assertSame(2, (new ScoreReport($graded))->chart()['count']);
    }

    public function test_an_expired_sitting_is_graded_and_readable(): void
    {
        [$user, $attempt] = $this->gradedPaper();

        $attempt->forceFill([
            'status' => \App\Enums\AttemptStatus::Expired,
            'total_score' => 300,
            'twk_score' => 100,
            'tiu_score' => 100,
            'tkp_score' => 100,
        ])->save();

        $this->actingAs($user)
            ->get('/hasil/'.$attempt->id)
            ->assertOk()
            ->assertSee('waktu habis');
    }
}
```

- [ ] **Step 8: Run the test to verify it fails**

Run: `php artisan test --filter=ResultPageTest`
Expected: FAIL — `Class "App\Services\Exam\ScoreReport" not found`.

- [ ] **Step 9: Run the test to verify it passes**

Run: `php artisan test --filter=ResultPageTest`
Expected: PASS, 10 tests.

- [ ] **Step 10: Run the whole suite**

Run: `php artisan test`
Expected: every test passes, including the homepage tests and the two updated redirect assertions.

- [ ] **Step 11: Commit**

```bash
git add app/Services/Exam/ScoreReport.php app/Http/Controllers/Exam/ResultController.php resources/views/exam routes/web.php tests/Feature/Exam
git commit -m "feat(exam): the score analysis page with its subtopic and progress breakdown"
```

---

### Task 12: Admin foundation — layout, gate, dashboard, first admin account

**Files:**
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `app/Console/Commands/CreateAdminCommand.php`
- Create: `resources/views/components/layouts/admin.blade.php`
- Create: `resources/views/admin/dashboard.blade.php`
- Create: `resources/icons/{grid,file-text,layers,users,upload,plus,pencil,trash,logout}.svg`
- Create: `routes/admin.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Admin/AdminAccessTest.php`, `tests/Feature/Admin/AdminCommandTest.php`, `tests/Feature/IconsTest.php`

**Interfaces:**
- Consumes: `EnsureUserIsAdmin` (alias `admin`, Task 8), `PackageComposition`, `Question`, `ExamPackage`, `User`.
- Produces: route group `admin.*` under `/admin` with middleware `web, auth, admin, password.changed`; `php artisan cpns:admin {email} {--name=} {--password=}`; the admin layout `<x-layouts.admin :title="$title" :site="$site">`.

- [ ] **Step 1: Add the admin icons**

Same shape as the exam icons (24×24, `stroke="currentColor"`, `fill="none"`, `stroke-width="2"`, round caps and joins):

- `grid.svg` — four rounded rectangles: `<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect>`
- `file-text.svg` — `<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"></path><polyline points="14 3 14 8 19 8"></polyline><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="13" y2="17"></line>`
- `layers.svg` — `<polygon points="12 3 3 8 12 13 21 8"></polygon><polyline points="3 13 12 18 21 13"></polyline>`
- `users.svg` — `<circle cx="9" cy="8" r="3.5"></circle><path d="M3 20c0-3 2.7-5 6-5s6 2 6 5"></path><path d="M16 5a3.2 3.2 0 0 1 0 6.4"></path><path d="M17.5 20c0-2.3-.8-4-2.2-5"></path>`
- `upload.svg` — `<path d="M12 16V4"></path><polyline points="7 9 12 4 17 9"></polyline><path d="M4 20h16"></path>`
- `plus.svg` — `<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>`
- `pencil.svg` — `<path d="M4 20l4-1L18.5 8.5a2.1 2.1 0 0 0-3-3L5 16l-1 4z"></path>`
- `trash.svg` — `<path d="M4 7h16"></path><path d="M9 7V4h6v3"></path><path d="M6 7l1 14h10l1-14"></path>`
- `logout.svg` — `<path d="M15 12H4"></path><polyline points="9 7 4 12 9 17"></polyline><path d="M12 4h6a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-6"></path>`

Wrap each in the root `<svg …>` element. Re-run the parse check from Task 9 Step 1.

- [ ] **Step 2: Write the admin layout**

`resources/views/components/layouts/admin.blade.php`:

```blade
@props(['title' => 'Admin', 'site' => []])

{{--
    The third layout in the application (marketing, exam, admin). It shares the
    token block with the other two, so it is recognisably the same product, but it
    has no marketing footer and keeps its own left sidebar.

    Nav items are a plain array here because they are presentation, not data: there
    is no second place that needs to know what the admin menu contains.
--}}
@php
    $nav = [
        ['route' => 'admin.dashboard', 'label' => 'Ringkasan', 'icon' => 'grid', 'pattern' => 'admin.dashboard'],
        ['route' => 'admin.questions.index', 'label' => 'Bank soal', 'icon' => 'file-text', 'pattern' => 'admin.questions.*'],
        ['route' => 'admin.packages.index', 'label' => 'Paket tryout', 'icon' => 'layers', 'pattern' => 'admin.packages.*'],
        ['route' => 'admin.import.index', 'label' => 'Impor soal', 'icon' => 'upload', 'pattern' => 'admin.import.*'],
        ['route' => 'admin.users.index', 'label' => 'Pengguna', 'icon' => 'users', 'pattern' => 'admin.users.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} — {{ $site['brand'] ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface font-body text-body antialiased">
    <div class="lg:flex">
        <aside class="border-b border-line bg-white lg:min-h-screen lg:w-[260px] lg:shrink-0 lg:border-r lg:border-b-0">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <span class="font-heading text-body font-bold text-ink">{{ $site['brand'] ?? config('app.name') }}</span>
                <span class="rounded-card bg-brand/10 px-2.5 py-1 text-caption font-semibold text-brand">Admin</span>
            </div>

            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:overflow-visible lg:px-3 lg:pb-6">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'inline-flex shrink-0 items-center gap-2.5 rounded-card px-3.5 py-2.5 text-small font-semibold transition',
                            'bg-brand/10 text-brand' => request()->routeIs($item['pattern']),
                            'text-muted hover:bg-surface hover:text-ink' => ! request()->routeIs($item['pattern']),
                        ])>
                        <x-icon :name="$item['icon']" class="h-4 w-4" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="hidden px-5 py-4 lg:block">
                <p class="text-caption text-muted">{{ auth()->user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 text-small font-semibold text-muted transition hover:text-danger">
                        <x-icon name="logout" class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-5 py-6 lg:px-8 lg:py-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
```

- [ ] **Step 3: Write the admin dashboard**

`app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(ContentRepository $content): View
    {
        $packages = ExamPackage::query()->withCount('questions')->orderBy('title')->get();

        // The list that actually gets used: packages that are not yet a real exam,
        // with the shortfall named per subtest.
        $incomplete = $packages
            ->filter(fn (ExamPackage $package): bool => ! PackageComposition::isComplete($package))
            ->map(fn (ExamPackage $package): array => [
                'package' => $package,
                'report' => PackageComposition::report($package),
                'shortfall' => PackageComposition::shortfallMessage($package),
            ])
            ->values();

        return view('admin.dashboard', [
            'site' => $content->site(),
            'title' => 'Ringkasan',
            'questionsPerCategory' => collect(QuestionCategory::cases())
                ->mapWithKeys(fn (QuestionCategory $category): array => [
                    $category->label() => Question::query()->category($category)->count(),
                ]),
            'questionTotal' => Question::query()->count(),
            'draftPackages' => $packages->where('status', ExamPackage::STATUS_DRAFT)->count(),
            'publishedPackages' => $packages->where('status', ExamPackage::STATUS_PUBLISHED)->count(),
            'participantCount' => User::query()->where('role', 'peserta')->count(),
            'attemptCount' => ExamAttempt::query()->count(),
            'incomplete' => $incomplete,
        ]);
    }
}
```

`resources/views/admin/dashboard.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Ringkasan</h1>
    <p class="mt-2 text-body text-muted">Keadaan bank soal dan paket tryout hari ini.</p>

    <div class="mt-6 grid gap-4 lg:grid-cols-4">
        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Total soal</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $questionTotal }}</p>
            <ul class="mt-3 space-y-1 text-small text-muted">
                @foreach ($questionsPerCategory as $label => $count)
                    <li class="flex justify-between gap-3"><span>{{ $label }}</span><span class="font-semibold text-ink">{{ $count }}</span></li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Paket</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $draftPackages + $publishedPackages }}</p>
            <p class="mt-3 text-small text-muted">{{ $draftPackages }} draft · {{ $publishedPackages }} published</p>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Peserta</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $participantCount }}</p>
            <p class="mt-3 text-small text-muted">{{ $attemptCount }} percobaan tercatat</p>
        </div>

        <div class="rounded-card border border-line bg-white p-5 shadow-card">
            <p class="text-caption font-semibold tracking-wide text-muted uppercase">Paket belum lengkap</p>
            <p class="mt-2 font-heading text-[30px] leading-none font-bold text-ink">{{ $incomplete->count() }}</p>
            <p class="mt-3 text-small text-muted">Paket yang belum genap {{ config('cpns.total_items') }} soal tidak bisa dibuka peserta.</p>
        </div>
    </div>

    <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Yang masih kurang</h2>
    <div class="mt-4 space-y-3">
        @forelse ($incomplete as $row)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $row['package']->title }}</strong>
                        <p class="text-small text-muted">{{ $row['shortfall'] }}</p>
                    </div>
                    <a href="{{ route('admin.packages.index') }}"
                        class="rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                        Susun paket
                    </a>
                </div>

                <ul class="mt-4 grid gap-2 text-small lg:grid-cols-3">
                    @foreach ($row['report'] as $subtest)
                        <li class="rounded-card bg-surface px-3.5 py-2.5">
                            <span class="font-semibold text-ink">{{ $subtest['label'] }}</span>
                            <span class="{{ $subtest['complete'] ? 'text-success' : 'text-danger' }}">
                                {{ $subtest['present'] }} / {{ $subtest['required'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                Semua paket sudah genap. Tidak ada yang perlu dilengkapi.
            </p>
        @endforelse
    </div>
</x-layouts.admin>
```

The dashboard's "Susun paket" button targets the package list for now, because the builder
route does not exist until Task 15. Task 15 Step 5 points it at
`route('admin.packages.builder', $row['package'])`, which is where it belongs. Every commit in
between renders a working page.

- [ ] **Step 4: Write the first-admin command**

`app/Console/Commands/CreateAdminCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Creates the first administrator. Deliberately not a seeder: a seeder ships a
 * default password that survives on a server, and this one either prompts for the
 * password or takes it from the command line.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'cpns:admin
        {email : Alamat email akun admin}
        {--name= : Nama yang ditampilkan}
        {--password= : Password awal; kalau kosong akan ditanyakan}';

    protected $description = 'Create or promote an admin account for the CPNS admin panel';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?? '');

        if ($password === '') {
            $password = (string) $this->secret('Password untuk akun admin');
        }

        if (strlen($password) < (int) config('cpns.password.min_length')) {
            $this->error('Password minimal '.config('cpns.password.min_length').' karakter.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing === null) {
            $user = User::create([
                'name' => (string) ($this->option('name') ?? Str::before($email, '@')),
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Admin,
            ]);

            $user->assignParticipantNumber();

            $this->info("Admin [{$email}] dibuat.");

            return self::SUCCESS;
        }

        $existing->forceFill([
            'role' => UserRole::Admin,
            'password' => $password,
            // A password typed into a shell is temporary by nature, so the account
            // is held at the change-password form.
            'must_change_password' => true,
        ])->save();

        $this->info("Akun [{$email}] dijadikan admin dan passwordnya diganti.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Write the admin routes and mount them**

`routes/admin.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 * The admin panel's own route file, so routes/web.php stays short enough to read in
 * one screen. The group's middleware, prefix and name are applied where it is
 * mounted, in bootstrap/app.php.
 */

Route::get('/', DashboardController::class)->name('dashboard');
```

`routes/admin.php` mentions only what exists at this point; Tasks 13–16 add their routes here as they create the controllers. Anything referenced before it exists would break every page that builds the admin nav, so the mount waits for each piece.

In `bootstrap/app.php`, add the import `use Illuminate\Support\Facades\Route;` and extend `withRouting`:

```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', 'auth', 'admin', 'password.changed'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
```

- [ ] **Step 6: Write the failing tests**

`tests/Feature/Admin/AdminAccessTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_a_peserta_gets_a_forbidden_page_on_every_admin_route(): void
    {
        $peserta = User::factory()->create();

        $this->actingAs($peserta)->get('/admin')->assertForbidden();
    }

    public function test_an_admin_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ringkasan');
    }

    public function test_the_dashboard_lists_packages_that_are_not_yet_complete(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Setengah Jadi']);

        $response = $this->actingAs(User::factory()->admin()->create())->get('/admin');

        $response->assertOk();
        $response->assertSee('Tryout Setengah Jadi');
        $response->assertSee('TWK kurang 30');
    }

    public function test_a_temporary_password_still_blocks_the_admin_panel(): void
    {
        $admin = User::factory()->admin()->create(['must_change_password' => true]);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('password.edit'));
    }
}
```

`tests/Feature/Admin/AdminCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_account(): void
    {
        $this->artisan('cpns:admin', [
            'email' => 'admin@example.test',
            '--name' => 'Petugas',
            '--password' => 'rahasia123',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('Petugas', $admin->name);
        $this->assertNotNull($admin->participant_number);
        $this->assertTrue(Hash::check('rahasia123', $admin->password));
        $this->assertFalse($admin->must_change_password);
    }

    public function test_promoting_an_existing_account_forces_a_password_change(): void
    {
        $user = User::factory()->create(['email' => 'naik@example.test']);

        $this->artisan('cpns:admin', ['email' => 'naik@example.test', '--password' => 'rahasia123'])
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->must_change_password);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->artisan('cpns:admin', ['email' => 'admin@example.test', '--password' => 'pendek'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
```

`tests/Feature/IconsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Icon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the house rule: every icon a view asks for is a real SVG file in
 * resources/icons. A typo in a name would otherwise only show up as a broken page
 * on production, and an emoji would never be caught at all.
 */
final class IconsTest extends TestCase
{
    public function test_every_icon_named_in_a_view_exists_as_an_svg_file(): void
    {
        $names = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/<x-icon\s+name="([a-z0-9-]+)"/', $file->getContents(), $matches);
            $names = array_merge($names, $matches[1]);

            preg_match_all("/'icon' => '([a-z0-9-]+)'/", $file->getContents(), $matches);
            $names = array_merge($names, $matches[1]);
        }

        $this->assertNotEmpty($names, 'No icon usages were found at all — the scan is broken.');

        foreach (array_unique($names) as $name) {
            $this->assertTrue(Icon::exists($name), "Icon [{$name}] is referenced but resources/icons/{$name}.svg does not exist.");
        }
    }

    public function test_no_view_uses_an_emoji_as_an_icon(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertSame(
                0,
                preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $file->getContents()),
                "A view contains an emoji, which the house rule forbids: {$file->getRelativePathname()}",
            );
        }
    }
}
```

- [ ] **Step 7: Run the tests to verify they fail**

Run: `php artisan test --filter='AdminAccessTest|AdminCommandTest|IconsTest'`
Expected: FAIL — `Route [admin.dashboard] not defined.` / missing icons.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter='AdminAccessTest|AdminCommandTest|IconsTest'`
Expected: PASS, 12 tests.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Admin app/Console/Commands resources/views/components/layouts/admin.blade.php resources/views/admin resources/icons routes/admin.php bootstrap/app.php tests/Feature/Admin tests/Feature/IconsTest.php
git commit -m "feat(admin): the panel shell, its gate, the dashboard and the first-admin command"
```

### Task 13: Question bank screen — list with blast radius, and the two-faced form

**Files:**
- Create: `app/Http/Requests/Admin/StoreQuestionRequest.php`, `app/Http/Requests/Admin/UpdateQuestionRequest.php`
- Create: `app/Http/Controllers/Admin/QuestionController.php`
- Create: `resources/views/admin/questions/{index,create,edit,form}.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/QuestionAdminTest.php`

**Interfaces:**
- Consumes: `Question`, `QuestionOption`, `QuestionCategory::subcategories()`, the `admin` layout.
- Produces: routes `admin.questions.index|create|store|edit|update|destroy`; the form payload shape `{category, subcategory, stem, explanation, is_active, key (TWK/TIU only), options[A..E][body], options[A..E][score] (TKP only)}`.

- [ ] **Step 1: Write the shared validation, used by store and update**

`app/Http/Requests/Admin/StoreQuestionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\QuestionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One item, two shapes.
 *
 * TWK/TIU: five options plus a single `key` letter. A score on one of their options
 * is refused outright, because a keyed item has no weights.
 *
 * TKP: five options with a weight each and no key at all — in TKP every option is
 * defensible and only the weight separates them.
 *
 * The rule the whole subtest rests on: a TKP item must carry the weights 1, 2, 3, 4
 * and 5 exactly once each. Two options sharing a weight makes the item worthless,
 * and it must never save.
 */
class StoreQuestionRequest extends FormRequest
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->category();

        $rules = [
            'category' => ['required', Rule::enum(QuestionCategory::class)],
            'subcategory' => ['required', 'string', Rule::in(array_keys($category?->subcategories() ?? []))],
            'stem' => ['required', 'string', 'min:10'],
            'explanation' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['required', 'array', 'size:5'],
        ];

        foreach (self::LETTERS as $letter) {
            $rules["options.{$letter}.body"] = ['required', 'string'];

            $rules["options.{$letter}.score"] = $category === QuestionCategory::Tkp
                ? ['required', 'integer', 'between:1,5']
                : ['prohibited'];
        }

        $rules['key'] = ($category === null || $category->requiresKey())
            ? ['required', Rule::in(self::LETTERS)]
            : ['prohibited'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->category();
            $options = $this->input('options');

            if ($category === null || ! is_array($options)) {
                return;
            }

            if (array_keys($options) !== [] && collect(array_keys($options))->sort()->values()->all() !== self::LETTERS) {
                $validator->errors()->add('options', 'Lima opsi harus berlabel A sampai E.');
            }

            if ($category !== QuestionCategory::Tkp) {
                return;
            }

            $scores = collect($options)
                ->map(fn ($option): int => (int) ($option['score'] ?? 0))
                ->sort()
                ->values()
                ->all();

            if ($scores !== [1, 2, 3, 4, 5]) {
                $validator->errors()->add(
                    'options',
                    'Bobot TKP harus tepat 1, 2, 3, 4, dan 5 — tidak boleh ada bobot kembar.',
                );
            }
        });
    }

    public function category(): ?QuestionCategory
    {
        return QuestionCategory::tryFrom((string) $this->input('category'));
    }

    /**
     * The item's own columns, ready for create() or fill().
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'category' => $this->validated('category'),
            'subcategory' => $this->validated('subcategory'),
            'stem' => $this->validated('stem'),
            'explanation' => $this->validated('explanation'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
```

`app/Http/Requests/Admin/UpdateQuestionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Editing an item is always allowed, including one that is already used in a
 * package that has been sat — the results page reads text live from the bank, so a
 * typo fix reaches past participants. What the rule protects is the score, which is
 * frozen on the answer rows and never moves. Identical validation.
 */
final class UpdateQuestionRequest extends StoreQuestionRequest
{
    //
}
```

- [ ] **Step 2: Write the controller**

`app/Http/Controllers/Admin/QuestionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Question;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class QuestionController extends Controller
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    public function index(Request $request, ContentRepository $content): View
    {
        $category = QuestionCategory::tryFrom((string) $request->query('category'));
        $search = (string) $request->query('q');

        $questions = Question::query()
            ->with(['options', 'packages:id,title'])
            ->when($category !== null, fn ($query) => $query->category($category))
            ->when($request->filled('subcategory'), fn ($query) => $query->where('subcategory', $request->query('subcategory')))
            ->when($search !== '', fn ($query) => $query->where('stem', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.questions.index', [
            'site' => $content->site(),
            'title' => 'Bank soal',
            'questions' => $questions,
            'category' => $category,
            'search' => $search,
            'subcategory' => (string) $request->query('subcategory'),
        ]);
    }

    public function create(ContentRepository $content): View
    {
        // The category is chosen before the form is drawn, so the form never has to
        // guess which of its two faces is the real one, and no field is ever
        // submitted that the rules would refuse.
        $category = QuestionCategory::tryFrom((string) request()->query('category'));

        return view('admin.questions.create', [
            'site' => $content->site(),
            'title' => 'Tambah soal',
            'category' => $category,
        ]);
    }

    public function store(StoreQuestionRequest $request): RedirectResponse
    {
        $question = Question::create($request->attributes());

        $this->syncOptions($question, $request);

        return redirect()
            ->route('admin.questions.index', ['category' => $question->category->value])
            ->with('status', 'Soal tersimpan.');
    }

    public function edit(Question $question, ContentRepository $content): View
    {
        $question->load('options');

        return view('admin.questions.edit', [
            'site' => $content->site(),
            'title' => 'Ubah soal',
            'question' => $question,
            'category' => $question->category,
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question): RedirectResponse
    {
        $question->update($request->attributes());

        $this->syncOptions($question, $request);

        return redirect()
            ->route('admin.questions.index', ['category' => $question->category->value])
            ->with('status', 'Perubahan tersimpan. Skor peserta yang sudah selesai tidak berubah.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();

        return back()->with('status', 'Soal dihapus dari bank.');
    }

    private function syncOptions(Question $question, StoreQuestionRequest $request): void
    {
        $options = (array) $request->validated('options');
        $key = $request->validated('key');

        foreach (self::LETTERS as $letter) {
            $question->options()->updateOrCreate(
                ['label' => $letter],
                [
                    'body' => $options[$letter]['body'],
                    'is_correct' => $key === null ? null : $letter === $key,
                    'score' => $key === null ? (int) $options[$letter]['score'] : null,
                ],
            );
        }
    }
}
```

- [ ] **Step 3: Write the three views**

`resources/views/admin/questions/index.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading text-h2 font-bold text-ink">Bank soal</h1>
            <p class="mt-1 text-body text-muted">{{ $questions->total() }} soal cocok dengan filter ini.</p>
        </div>
        <a href="{{ route('admin.questions.create') }}" class="inline-flex items-center gap-2 rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Tambah soal
        </a>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    <form method="GET" action="{{ route('admin.questions.index') }}" class="mt-6 grid gap-3 lg:grid-cols-[200px_220px_minmax(0,1fr)_auto]">
        <select name="category" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <option value="">Semua subtes</option>
            @foreach (\App\Enums\QuestionCategory::cases() as $option)
                <option value="{{ $option->value }}" @selected($category === $option)>{{ $option->label() }}</option>
            @endforeach
        </select>

        <select name="subcategory" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <option value="">Semua submateri</option>
            @foreach (($category?->subcategories() ?? []) as $value => $label)
                <option value="{{ $value }}" @selected($subcategory === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $search }}" placeholder="Cari teks soal"
            class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">

        <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">Saring</button>
    </form>

    <div class="mt-5 overflow-x-auto rounded-card border border-line bg-white shadow-card">
        <table class="w-full min-w-[860px] text-left text-small">
            <thead class="bg-surface text-caption uppercase text-muted">
                <tr>
                    <th class="px-4 py-3">Soal</th>
                    <th class="px-4 py-3">Subtes</th>
                    <th class="px-4 py-3">Submateri</th>
                    <th class="px-4 py-3">Dipakai di</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($questions as $question)
                    <tr class="border-t border-line align-top">
                        <td class="max-w-[420px] px-4 py-3 text-ink">{{ \Illuminate\Support\Str::limit($question->stem, 110) }}</td>
                        <td class="px-4 py-3">{{ $question->category->label() }}</td>
                        <td class="px-4 py-3 text-muted">{{ $question->subcategoryLabel() }}</td>
                        {{-- The blast radius, before anyone edits anything. --}}
                        <td class="px-4 py-3" title="{{ $question->packages->pluck('title')->join(', ') }}">
                            @if ($question->packages->isEmpty())
                                <span class="text-muted">belum dipakai</span>
                            @else
                                <span class="font-semibold text-ink">{{ $question->packages->count() }} paket</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="{{ $question->is_active ? 'text-success' : 'text-muted' }}">
                                {{ $question->is_active ? 'aktif' : 'nonaktif' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a href="{{ route('admin.questions.edit', $question) }}" class="inline-flex items-center gap-1.5 font-semibold text-brand hover:text-brand-dark">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                    Ubah
                                </a>
                                <form method="POST" action="{{ route('admin.questions.destroy', $question) }}"
                                    onsubmit="return confirm('Hapus soal ini dari bank? Paket yang memakainya akan kehilangan soal tersebut.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1.5 font-semibold text-muted transition hover:text-danger">
                                        <x-icon name="trash" class="h-4 w-4" />
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-muted">Belum ada soal yang cocok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $questions->links() }}</div>
</x-layouts.admin>
```

`resources/views/admin/questions/create.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Tambah soal</h1>

    @if ($category === null)
        <p class="mt-2 text-body text-muted">Pilih subtesnya dulu — bentuk formnya berbeda antara TWK/TIU dan TKP.</p>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            @foreach (\App\Enums\QuestionCategory::cases() as $option)
                <a href="{{ route('admin.questions.create', ['category' => $option->value]) }}"
                    class="rounded-card border border-line bg-white p-5 shadow-card transition hover:border-brand">
                    <strong class="font-heading text-body font-bold text-ink">{{ $option->label() }}</strong>
                    <p class="mt-1 text-small text-muted">{{ $option->name() }}</p>
                    <p class="mt-3 text-caption text-muted">
                        {{ $option->itemCount() }} soal per paket ·
                        {{ $option->requiresKey() ? 'satu kunci jawaban' : 'bobot 1-5 per opsi' }}
                    </p>
                </a>
            @endforeach
        </div>
    @else
        @include('admin.questions.form', [
            'question' => null,
            'category' => $category,
            'action' => route('admin.questions.store'),
            'method' => 'POST',
        ])
    @endif
</x-layouts.admin>
```

`resources/views/admin/questions/edit.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Ubah soal</h1>
    <p class="mt-2 text-body text-muted">
        Soal boleh diubah kapan saja. Teks dan pembahasan dibaca langsung dari bank, jadi perbaikan
        langsung terlihat peserta lama; yang tidak berubah adalah skor percobaan yang sudah selesai.
    </p>

    @include('admin.questions.form', [
        'question' => $question,
        'category' => $category,
        'action' => route('admin.questions.update', $question),
        'method' => 'PUT',
    ])
</x-layouts.admin>
```

`resources/views/admin/questions/form.blade.php`:

```blade
@props(['question', 'category', 'action', 'method'])

@php
    $options = $question?->options->keyBy('label') ?? collect();
    $key = $question?->options->firstWhere('is_correct', true)?->label;
@endphp

@if ($errors->any())
    <ul class="mt-5 space-y-1 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="POST" action="{{ $action }}" class="mt-6 space-y-5">
    @csrf
    @method($method)

    <input type="hidden" name="category" value="{{ $category->value }}">

    <div class="grid gap-4 rounded-card border border-line bg-white p-5 shadow-card lg:grid-cols-2">
        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Submateri</span>
            <select name="subcategory" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                @foreach ($category->subcategories() as $value => $label)
                    <option value="{{ $value }}" @selected(old('subcategory', $question?->subcategory) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Status</span>
            <select name="is_active" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                <option value="1" @selected(old('is_active', $question?->is_active ?? true))>Aktif</option>
                <option value="0" @selected(! old('is_active', $question?->is_active ?? true))>Nonaktif (tidak dipakai paket baru)</option>
            </select>
        </label>

        <label class="block lg:col-span-2">
            <span class="mb-1.5 block text-small font-semibold text-ink">Teks soal</span>
            <textarea name="stem" rows="4" required
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('stem', $question?->stem) }}</textarea>
        </label>
    </div>

    <div class="space-y-3 rounded-card border border-line bg-white p-5 shadow-card">
        <p class="text-small font-semibold text-ink">
            Lima opsi
            @if ($category->requiresKey())
                <span class="font-normal text-muted">— pilih satu kunci jawaban</span>
            @else
                <span class="font-normal text-muted">— beri bobot 1 sampai 5, masing-masing dipakai sekali</span>
            @endif
        </p>

        @foreach (['A', 'B', 'C', 'D', 'E'] as $letter)
            <div class="grid gap-3 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-start">
                @if ($category->requiresKey())
                    <label class="flex items-center gap-2 rounded-card border border-line px-3.5 py-3 lg:self-stretch">
                        <input type="radio" name="key" value="{{ $letter }}" @checked(old('key', $key) === $letter)>
                        <span class="font-heading text-small font-bold text-ink">{{ $letter }}</span>
                        <span class="text-caption text-muted">kunci</span>
                    </label>
                @else
                    <span class="flex items-center justify-center rounded-card border border-line px-3.5 py-3 font-heading text-small font-bold text-muted">{{ $letter }}</span>
                @endif

                <input type="text" name="options[{{ $letter }}][body]" required
                    value="{{ old("options.{$letter}.body", $options->get($letter)?->body) }}"
                    placeholder="Isi opsi {{ $letter }}"
                    class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">

                @unless ($category->requiresKey())
                    <label class="flex items-center gap-2">
                        <span class="text-caption text-muted">Bobot</span>
                        <select name="options[{{ $letter }}][score]" class="rounded-card border border-line px-3 py-2.5 text-small text-ink">
                            @foreach (range(1, 5) as $score)
                                <option value="{{ $score }}" @selected((int) old("options.{$letter}.score", $options->get($letter)?->score) === $score)>{{ $score }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless
            </div>
        @endforeach
    </div>

    <label class="block rounded-card border border-line bg-white p-5 shadow-card">
        <span class="mb-1.5 block text-small font-semibold text-ink">Pembahasan</span>
        <span class="mb-2 block text-caption text-muted">Muncul di halaman analisa begitu peserta menyelesaikan ujian.</span>
        <textarea name="explanation" rows="3"
            class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('explanation', $question?->explanation) }}</textarea>
    </label>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            Simpan soal
        </button>
        <a href="{{ route('admin.questions.index') }}" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">
            Batal
        </a>
    </div>
</form>
```

- [ ] **Step 4: Add the routes**

In `routes/admin.php`, add the import and:

```php
Route::get('/soal', [QuestionController::class, 'index'])->name('questions.index');
Route::get('/soal/baru', [QuestionController::class, 'create'])->name('questions.create');
Route::post('/soal', [QuestionController::class, 'store'])->name('questions.store');
Route::get('/soal/{question}/ubah', [QuestionController::class, 'edit'])->name('questions.edit');
Route::put('/soal/{question}', [QuestionController::class, 'update'])->name('questions.update');
Route::delete('/soal/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
```

- [ ] **Step 5: Write the failing test**

`tests/Feature/Admin/QuestionAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\QuestionCategory;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuestionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function twkPayload(array $overrides = []): array
    {
        return $overrides + [
            'category' => 'twk',
            'subcategory' => 'pilar_negara',
            'stem' => 'Pancasila sebagai dasar negara mengandung konsekuensi bahwa',
            'explanation' => 'Karena Pancasila menjadi sumber dari segala sumber hukum.',
            'is_active' => '1',
            'key' => 'C',
            'options' => [
                'A' => ['body' => 'Opsi A'],
                'B' => ['body' => 'Opsi B'],
                'C' => ['body' => 'Opsi C'],
                'D' => ['body' => 'Opsi D'],
                'E' => ['body' => 'Opsi E'],
            ],
        ];
    }

    private function tkpPayload(array $scores = [5, 4, 3, 2, 1]): array
    {
        return [
            'category' => 'tkp',
            'subcategory' => 'pelayanan_publik',
            'stem' => 'Seorang warga mengeluh di loket tempatmu bertugas, sikapmu adalah',
            'explanation' => 'Yang paling selaras dengan nilai pelayanan publik.',
            'is_active' => '1',
            'options' => [
                'A' => ['body' => 'Opsi A', 'score' => $scores[0]],
                'B' => ['body' => 'Opsi B', 'score' => $scores[1]],
                'C' => ['body' => 'Opsi C', 'score' => $scores[2]],
                'D' => ['body' => 'Opsi D', 'score' => $scores[3]],
                'E' => ['body' => 'Opsi E', 'score' => $scores[4]],
            ],
        ];
    }

    public function test_a_peserta_cannot_reach_the_bank(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/soal')->assertForbidden();
    }

    public function test_an_admin_creates_a_keyed_item_with_exactly_one_key(): void
    {
        $this->actingAs($this->admin())->post('/admin/soal', $this->twkPayload())
            ->assertRedirect(route('admin.questions.index', ['category' => 'twk']));

        $question = Question::query()->firstOrFail();

        $this->assertSame(QuestionCategory::Twk, $question->category);
        $this->assertSame(5, $question->options()->count());
        $this->assertSame(1, $question->options()->where('is_correct', true)->count());
        $this->assertSame('C', $question->correctOption()->label);
        $this->assertSame(0, $question->options()->whereNotNull('score')->count());
    }

    public function test_a_tkp_item_saves_with_a_full_weight_ladder(): void
    {
        $this->actingAs($this->admin())->post('/admin/soal', $this->tkpPayload())->assertRedirect();

        $question = Question::query()->firstOrFail();

        $this->assertSame([1, 2, 3, 4, 5], $question->options->pluck('score')->sort()->values()->all());
        $this->assertSame(0, $question->options()->whereNotNull('is_correct')->count());
    }

    public function test_a_tkp_item_with_a_repeated_weight_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/soal', $this->tkpPayload([5, 5, 3, 2, 1]))
            ->assertSessionHasErrors('options');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_keyed_item_carrying_weights_is_refused(): void
    {
        $payload = $this->twkPayload();
        $payload['options']['A']['score'] = 3;

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('options.A.score');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_tkp_item_carrying_a_key_is_refused(): void
    {
        $payload = $this->tkpPayload();
        $payload['key'] = 'A';

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('key');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_subcategory_outside_the_kisi_kisi_is_refused(): void
    {
        $payload = $this->twkPayload();
        $payload['subcategory'] = 'entah_apa';

        $this->actingAs($this->admin())->post('/admin/soal', $payload)->assertSessionHasErrors('subcategory');
    }

    public function test_editing_an_item_in_a_package_that_has_been_sat_still_works(): void
    {
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->first();
        $attempt = \App\Models\ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->submitted()
            ->create(['total_score' => 200]);

        $payload = $this->twkPayload([
            'category' => 'twk',
            'subcategory' => $question->subcategory,
            'stem' => 'Teks soal yang sudah diperbaiki dan lebih panjang dari sebelumnya',
            'key' => $question->correctOption()->label,
        ]);

        $this->actingAs($this->admin())
            ->put('/admin/soal/'.$question->id, $payload)
            ->assertRedirect();

        $this->assertSame('Teks soal yang sudah diperbaiki dan lebih panjang dari sebelumnya', $question->refresh()->stem);
        $this->assertSame(200, $attempt->refresh()->total_score, 'The frozen score must not move.');
    }

    public function test_the_list_shows_which_packages_use_an_item(): void
    {
        $package = ExamPackage::factory()->create(['title' => 'Tryout Contoh']);
        $package->addQuestion(Question::factory()->twk()->create());

        $response = $this->actingAs($this->admin())->get('/admin/soal');

        $response->assertOk();
        $response->assertSee('1 paket');
        $response->assertSee('Tryout Contoh', escape: false);
    }
}
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test --filter=QuestionAdminTest`
Expected: FAIL — `Route [admin.questions.index] not defined.`

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=QuestionAdminTest`
Expected: PASS, 9 tests. If `test_the_list_shows_which_packages_use_an_item` fails on the package title, the `title` attribute needs the titles joined in the view — that is what the assertion is checking.

- [ ] **Step 8: Run Pint and commit**

```bash
vendor/bin/pint
git add app/Http/Requests/Admin app/Http/Controllers/Admin/QuestionController.php resources/views/admin/questions routes/admin.php tests/Feature/Admin/QuestionAdminTest.php
git commit -m "feat(admin): the question bank list and the two-faced question form"
```

---

### Task 14: Bulk import — parser, dry-run preview, all-or-nothing commit

**Files:**
- Create: `app/Support/QuestionParser.php`, `app/Http/Requests/Admin/ImportQuestionsRequest.php`
- Create: `app/Http/Controllers/Admin/ImportController.php`
- Create: `resources/views/admin/import/index.blade.php`
- Create: `docs/QUESTION-IMPORT-FORMAT.md`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/ImportTest.php`

**Interfaces:**
- Consumes: `Question`, `QuestionCategory::subcategories()`, `StoreQuestionRequest`'s two payload shapes (the parser emits the same structure the form posts, so validation and creation share one path).
- Produces: `QuestionParser::parse(string $payload): array{rows: array<int, array>, errors: array<int, string>}`; routes `admin.import.index`, `admin.import.preview`, `admin.import.commit`.

- [ ] **Step 1: Write the parser**

`app/Support/QuestionParser.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionCategory;

/**
 * Turns a pasted block or a CSV body into rows the question form's own validation
 * would also accept, plus a per-line list of what is wrong with the rest.
 *
 * Two formats, one header row required:
 *
 *   TWK/TIU  category, subcategory, stem, option_a…option_e, key, explanation
 *   TKP      category, subcategory, stem, option_a…option_e, score_a…score_e
 *
 * Delimiter is a tab when the header contains one (what you get by pasting out of
 * a spreadsheet) and a comma otherwise.
 */
final class QuestionParser
{
    private const LETTERS = ['a', 'b', 'c', 'd', 'e'];

    /**
     * @return array{rows: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public function parse(string $payload): array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return ['rows' => [], 'errors' => [1 => 'Tidak ada isi yang bisa dibaca.']];
        }

        $delimiter = str_contains(strtok($payload, "\n"), "\t") ? "\t" : ',';
        $lines = preg_split('/\r\n|\r|\n/', $payload) ?: [];
        $header = $this->header(array_shift($lines), $delimiter);

        if ($header === []) {
            return ['rows' => [], 'errors' => [1 => 'Baris pertama harus berisi nama kolom.']];
        }

        $rows = [];
        $errors = [];

        foreach ($lines as $offset => $line) {
            $lineNumber = $offset + 2;

            if (trim($line) === '') {
                continue;
            }

            $values = array_map('trim', str_getcsv($line, $delimiter));

            if (count($values) < count($header)) {
                $values = array_pad($values, count($header), '');
            }

            $record = array_combine($header, array_slice($values, 0, count($header)));

            if ($record === false) {
                $errors[$lineNumber] = 'Jumlah kolom tidak cocok dengan baris judul.';

                continue;
            }

            [$row, $error] = $this->row($record);

            if ($error !== null) {
                $errors[$lineNumber] = $error;

                continue;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array<int, string>
     */
    private function header(string|false $line, string $delimiter): array
    {
        if ($line === false) {
            return [];
        }

        return array_map(
            fn (string $column): string => strtolower(trim($column)),
            str_getcsv($line, $delimiter),
        );
    }

    /**
     * @param  array<string, string>  $record
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function row(array $record): array
    {
        $category = QuestionCategory::tryFrom(strtolower($record['category'] ?? ''));

        if ($category === null) {
            return [null, 'Kolom category harus twk, tiu, atau tkp.'];
        }

        if (! array_key_exists($record['subcategory'] ?? '', $category->subcategories())) {
            return [null, 'Submateri ['.($record['subcategory'] ?? '').'] tidak ada di kisi-kisi '.$category->label().'.'];
        }

        $stem = $record['stem'] ?? '';

        if (mb_strlen($stem) < 10) {
            return [null, 'Teks soal terlalu pendek.'];
        }

        $options = [];

        foreach (self::LETTERS as $letter) {
            $body = $record['option_'.$letter] ?? '';

            if ($body === '') {
                return [null, 'Opsi '.strtoupper($letter).' masih kosong.'];
            }

            $options[strtoupper($letter)] = ['body' => $body];
        }

        $row = [
            'category' => $category->value,
            'subcategory' => $record['subcategory'],
            'stem' => $stem,
            'explanation' => ($record['explanation'] ?? '') === '' ? null : $record['explanation'],
            'is_active' => true,
            'options' => $options,
        ];

        if ($category->requiresKey()) {
            $key = strtoupper($record['key'] ?? '');

            if (! in_array($key, ['A', 'B', 'C', 'D', 'E'], true)) {
                return [null, 'Kolom key harus salah satu dari A sampai E.'];
            }

            $row['key'] = $key;

            return [$row, null];
        }

        $scores = [];

        foreach (self::LETTERS as $letter) {
            $score = (int) ($record['score_'.$letter] ?? 0);
            $scores[strtoupper($letter)] = $score;
            $row['options'][strtoupper($letter)]['score'] = $score;
        }

        $sorted = $scores;
        sort($sorted);

        if ($sorted !== [1, 2, 3, 4, 5]) {
            return [null, 'Bobot TKP harus tepat 1, 2, 3, 4, dan 5 — tidak boleh ada bobot kembar.'];
        }

        return [$row, null];
    }
}
```

- [ ] **Step 2: Write the import request and controller**

`app/Http/Requests/Admin/ImportQuestionsRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ImportQuestionsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Either a pasted block or an uploaded file, never neither.
            'payload' => ['required_without:file', 'nullable', 'string'],
            'file' => ['required_without:payload', 'nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * The uploaded file wins when both are present, because that is what the user
     * most recently chose to do.
     */
    public function payload(): string
    {
        if ($this->hasFile('file')) {
            return (string) file_get_contents($this->file('file')->getRealPath());
        }

        return (string) $this->input('payload');
    }
}
```

`app/Http/Controllers/Admin/ImportController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportQuestionsRequest;
use App\Models\Question;
use App\Support\ContentRepository;
use App\Support\QuestionParser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * All-or-nothing on purpose. A half-finished import into a live bank is far worse
 * than a refused one: a broken item is only discovered when a participant meets it,
 * and by then somebody's sitting is already spoiled.
 */
final class ImportController extends Controller
{
    public function index(ContentRepository $content): View
    {
        return view('admin.import.index', [
            'site' => $content->site(),
            'title' => 'Impor soal',
            'rows' => null,
            'errors' => [],
            'payload' => '',
        ]);
    }

    public function preview(ImportQuestionsRequest $request, ContentRepository $content, QuestionParser $parser): View
    {
        $payload = $request->payload();
        $parsed = $parser->parse($payload);

        return view('admin.import.index', [
            'site' => $content->site(),
            'title' => 'Impor soal',
            'rows' => $parsed['rows'],
            'errors' => $parsed['errors'],
            'payload' => $payload,
        ]);
    }

    public function commit(ImportQuestionsRequest $request, QuestionParser $parser): RedirectResponse
    {
        $payload = $request->payload();
        $parsed = $parser->parse($payload);

        // Nothing is written unless the preview would have been clean too, so a
        // hand-crafted POST cannot skip the check.
        if ($parsed['errors'] !== []) {
            return back()->withErrors(['payload' => 'Masih ada baris yang salah. Perbaiki dulu, tidak ada yang diimpor.']);
        }

        if ($parsed['rows'] === []) {
            return back()->withErrors(['payload' => 'Tidak ada baris yang bisa diimpor.']);
        }

        DB::transaction(function () use ($parsed): void {
            foreach ($parsed['rows'] as $row) {
                $options = $row['options'];
                $key = $row['key'] ?? null;

                $question = Question::create([
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'],
                    'stem' => $row['stem'],
                    'explanation' => $row['explanation'],
                    'is_active' => $row['is_active'],
                ]);

                foreach ($options as $letter => $option) {
                    $question->options()->create([
                        'label' => $letter,
                        'body' => $option['body'],
                        'is_correct' => $key === null ? null : $letter === $key,
                        'score' => $key === null ? (int) $option['score'] : null,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.import.index')
            ->with('status', count($parsed['rows']).' soal berhasil diimpor.');
    }
}
```

- [ ] **Step 3: Write the import view**

`resources/views/admin/import/index.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Impor soal</h1>
    <p class="mt-2 max-w-[70ch] text-body text-muted">
        Tempel blok berkolom atau unggah CSV. Semuanya lewat pratinjau dulu: kalau ada satu baris yang
        salah, tidak ada satu pun yang masuk.
    </p>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <p class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('admin.import.preview') }}" class="mt-6 space-y-4 rounded-card border border-line bg-white p-5 shadow-card">
        @csrf

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Tempel di sini</span>
            <textarea name="payload" rows="8" class="w-full rounded-card border border-line px-3.5 py-2.5 font-mono text-caption text-ink">{{ $payload }}</textarea>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Atau unggah berkas CSV</span>
            <input type="file" name="file" accept=".csv,.txt" class="text-small text-ink">
        </label>

        <button type="submit" class="rounded-card border border-line bg-white px-5 py-2.5 font-heading text-small font-bold text-ink">
            Pratinjau
        </button>
    </form>

    @if ($rows !== null)
        <div class="mt-6 rounded-card border border-line bg-white p-5 shadow-card">
            <h2 class="font-heading text-h3 font-bold text-ink">Pratinjau</h2>
            <p class="mt-1 text-small text-muted">
                {{ count($rows) }} baris siap diimpor · {{ count($errors) }} baris bermasalah
            </p>

            @if ($errors !== [])
                <ul class="mt-4 space-y-1.5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
                    @foreach ($errors as $line => $message)
                        <li>Baris {{ $line }}: {{ $message }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($rows !== [])
                <ol class="mt-4 space-y-3 text-small">
                    @foreach (array_slice($rows, 0, 10) as $index => $row)
                        <li class="border-t border-line pt-3 first:border-0 first:pt-0">
                            <span class="text-caption font-semibold text-muted uppercase">
                                {{ strtoupper($row['category']) }} · {{ $row['subcategory'] }}
                            </span>
                            <p class="mt-1 text-ink">{{ \Illuminate\Support\Str::limit($row['stem'], 140) }}</p>
                            <p class="mt-1 text-caption text-muted">
                                @foreach ($row['options'] as $letter => $option)
                                    {{ $letter }}{{ isset($option['score']) ? ':'.$option['score'] : '' }}{{ ($row['key'] ?? null) === $letter ? '*' : '' }}
                                @endforeach
                            </p>
                        </li>
                    @endforeach
                </ol>

                @if (count($rows) > 10)
                    <p class="mt-3 text-caption text-muted">… dan {{ count($rows) - 10 }} baris lain.</p>
                @endif

                <form method="POST" action="{{ route('admin.import.commit') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $payload }}">
                    <button type="submit" @disabled($errors !== [])
                        class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark disabled:opacity-40">
                        Impor {{ count($rows) }} soal
                    </button>
                    @if ($errors !== [])
                        <p class="mt-2 text-caption text-danger">Tombol ini aktif setelah semua baris bersih.</p>
                    @endif
                </form>
            @endif
        </div>
    @endif
</x-layouts.admin>
```

- [ ] **Step 4: Write the format documentation**

`docs/QUESTION-IMPORT-FORMAT.md` — complete content:

````markdown
# Question import format

Match the header row exactly; the column order does not matter but the names do.

## TWK and TIU

Header: `category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation`

```csv
category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation
twk,pilar_negara,"Pancasila sebagai dasar negara mengandung konsekuensi bahwa","Negara boleh mengabaikannya","Hukum harus bersumber padanya","Pancasila hanya simbol","Nilai Pancasila bersifat sementara","Pancasila hanya untuk pejabat",B,"Pancasila adalah sumber dari segala sumber hukum, jadi setiap aturan harus dapat ditelusuri ke sana."
tiu,deret_angka,"Lanjutkan pola 3, 6, 11, 18, ...","25","26","27","28","29",C,"Selisihnya bertambah dua tiap langkah: 3, 5, 7, 9, maka berikutnya 27."
```

Rules: `category` is `twk`, `tiu`, or `tkp`. `subcategory` must be one of the keys listed in
`config/cpns.php`. `key` is a single letter A–E. `explanation` may be empty.

## TKP

Header: `category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,score_a,score_b,score_c,score_d,score_e`
and no `key` column at all, because in TKP every option is defensible and only the weight
separates them.

```csv
category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,score_a,score_b,score_c,score_d,score_e
tkp,pelayanan_publik,"Seorang warga mengeluh keras di loketmu karena berkasnya kurang, sikapmu adalah","Menyuruhnya pulang dan kembali besok","Meminta rekan lain melayaninya","Menjelaskan kekurangan berkas dengan tenang lalu menunjukkan cara melengkapinya","Menyebut aturan dan menyuruhnya membaca sendiri","Mendiamkannya sampai ia berhenti mengeluh",1,3,5,2,1
```

The five scores must be exactly 1, 2, 3, 4 and 5 — each used once. Two options sharing a
weight makes the item worthless, because the subtest is scored by the weight of the chosen
option, and the importer refuses the whole batch when it finds one.

## Delimiter

A pasted block pasted out of a spreadsheet uses tabs; a CSV file uses commas. The parser
picks the delimiter by looking at the header row, so both work without a setting.

## What happens on error

Every row is checked before anything is written. The preview lists the failing line numbers
with the reason, and the import button stays disabled: **one bad row means nothing is
imported**. That is deliberate — a half-imported bank hides broken items until a participant
meets one, which is much more expensive than a refused import.
````

- [ ] **Step 5: Add the routes**

In `routes/admin.php`:

```php
Route::get('/impor', [ImportController::class, 'index'])->name('import.index');
Route::post('/impor/pratinjau', [ImportController::class, 'preview'])->name('import.preview');
Route::post('/impor', [ImportController::class, 'commit'])->name('import.commit');
```

- [ ] **Step 6: Write the failing test**

`tests/Feature/Admin/ImportTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\QuestionCategory;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'category,subcategory,stem,option_a,option_b,option_c,option_d,option_e,key,explanation';

    private function twkRow(string $stem = 'Soal impor pertama yang cukup panjang'): string
    {
        return "twk,pilar_negara,\"{$stem}\",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B,\"Pembahasan singkat.\"";
    }

    private function tkpRow(string $scores = '1,2,3,4,5'): string
    {
        return 'tkp,pelayanan_publik,"Soal TKP impor yang cukup panjang",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,'.$scores;
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_clean_block_is_imported(): void
    {
        $payload = self::HEADER."\n".$this->twkRow()."\n".$this->tkpRow();

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertRedirect(route('admin.import.index'))
            ->assertSessionHas('status');

        $this->assertSame(2, Question::query()->count());
        $this->assertSame(1, Question::query()->category(QuestionCategory::Tkp)->count());
        $this->assertSame(
            [1, 2, 3, 4, 5],
            Question::query()->category(QuestionCategory::Tkp)->first()->options->pluck('score')->sort()->values()->all(),
        );
    }

    public function test_a_pasted_tab_separated_block_works_too(): void
    {
        $payload = str_replace(',', "\t", self::HEADER)."\n".
            "twk\tpilar_negara\tSoal dengan pemisah tab dan cukup panjang\tOpsi A\tOpsi B\tOpsi C\tOpsi D\tOpsi E\tA\tPembahasan.";

        $this->actingAs($this->admin())->post('/admin/impor', ['payload' => $payload])->assertRedirect();

        $this->assertSame(1, Question::query()->count());
        $this->assertSame('A', Question::query()->firstOrFail()->correctOption()->label);
    }

    public function test_one_bad_row_writes_nothing_and_names_its_line(): void
    {
        $payload = self::HEADER."\n".$this->twkRow()."\n".'twk,entah_apa,"Soal dengan submateri salah",Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,B,';

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertOk();
        $response->assertSee('Baris 3');

        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => $payload])
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, Question::query()->count());
    }

    public function test_a_tkp_row_with_a_repeated_weight_is_reported_by_line(): void
    {
        $payload = self::HEADER."\n".$this->tkpRow('5,5,3,2,1');

        $response = $this->actingAs($this->admin())->post('/admin/impor/pratinjau', ['payload' => $payload]);

        $response->assertSee('Baris 2');
        $response->assertSee('bobot kembar', escape: false);

        $this->assertEquals(0, Question::query()->count());
    }

    public function test_an_empty_payload_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/impor', ['payload' => ''])
            ->assertSessionHasErrors('payload');
    }

    public function test_a_peserta_cannot_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/impor', ['payload' => self::HEADER."\n".$this->twkRow()])
            ->assertForbidden();
    }
}
```

- [ ] **Step 7: Run the test to verify it fails**

Run: `php artisan test --filter=ImportTest`
Expected: FAIL — `Class "App\Support\QuestionParser" not found`.

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=ImportTest`
Expected: PASS, 6 tests.

- [ ] **Step 9: Commit**

```bash
git add app/Support/QuestionParser.php app/Http/Requests/Admin/ImportQuestionsRequest.php app/Http/Controllers/Admin/ImportController.php resources/views/admin/import routes/admin.php docs/QUESTION-IMPORT-FORMAT.md tests/Feature/Admin/ImportTest.php
git commit -m "feat(admin): all-or-nothing question import with a dry-run preview"
```

---

### Task 15: Package builder — composition, ordering, publish, and the lock

**Files:**
- Create: `app/Http/Requests/Admin/BuildPackageRequest.php`
- Create: `app/Http/Controllers/Admin/PackageController.php`, `app/Http/Controllers/Admin/PackageBuilderController.php`
- Create: `resources/views/admin/packages/{index,create,builder}.blade.php`
- Modify: `routes/admin.php`, `resources/views/admin/dashboard.blade.php` (the button now targets the builder)
- Test: `tests/Feature/Admin/PackageAdminTest.php`

**Interfaces:**
- Consumes: `PackageComposition`, `ExamPackage::addQuestion()`, `::nextPosition()`, `::isLocked()`, `Question`.
- Produces: routes `admin.packages.index|create|store|edit|update|builder|attach|detach|move|publish|unpublish`.

- [ ] **Step 1: Write the package form request**

`app/Http/Requests/Admin/BuildPackageRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BuildPackageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('exam_packages', 'slug')->ignore($this->route('package')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'duration_minutes' => ['required', 'integer', 'between:10,300'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('title'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->validated('title'),
            'slug' => $this->validated('slug'),
            'description' => $this->validated('description'),
            'duration_minutes' => (int) $this->validated('duration_minutes'),
        ];
    }
}
```

- [ ] **Step 2: Write the package controller (index, create, store, edit, update)**

`app/Http/Controllers/Admin/PackageController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BuildPackageRequest;
use App\Models\ExamPackage;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PackageController extends Controller
{
    public function index(ContentRepository $content): View
    {
        $packages = ExamPackage::query()->withCount('questions', 'attempts')->orderBy('title')->get();

        return view('admin.packages.index', [
            'site' => $content->site(),
            'title' => 'Paket tryout',
            'packages' => $packages->map(fn (ExamPackage $package): array => [
                'package' => $package,
                'complete' => PackageComposition::isComplete($package),
                'shortfall' => PackageComposition::shortfallMessage($package),
            ]),
        ]);
    }

    public function create(ContentRepository $content): View
    {
        return view('admin.packages.create', [
            'site' => $content->site(),
            'title' => 'Paket baru',
        ]);
    }

    public function store(BuildPackageRequest $request): RedirectResponse
    {
        $package = ExamPackage::create($request->attributes());

        return redirect()
            ->route('admin.packages.builder', $package)
            ->with('status', 'Paket dibuat. Sekarang isi '.config('cpns.total_items').' soalnya.');
    }

    public function edit(ExamPackage $package, ContentRepository $content): View
    {
        return view('admin.packages.create', [
            'site' => $content->site(),
            'title' => 'Ubah paket',
            'package' => $package,
        ]);
    }

    public function update(BuildPackageRequest $request, ExamPackage $package): RedirectResponse
    {
        $package->update($request->attributes());

        return redirect()
            ->route('admin.packages.builder', $package)
            ->with('status', 'Paket diperbarui.');
    }
}
```

- [ ] **Step 3: Write the builder controller**

`app/Http/Controllers/Admin/PackageBuilderController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionCategory;
use App\Http\Controllers\Controller;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Support\ContentRepository;
use App\Support\PackageComposition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Composing a package by hand, one item at a time.
 *
 * Once anyone has sat a package its composition is frozen: two sittings of
 * different questions are not comparable on the history chart. The items
 * themselves stay editable from the bank, so a typo is still fixable — it is only
 * the list and its order that lock.
 */
final class PackageBuilderController extends Controller
{
    public function show(ExamPackage $package, ContentRepository $content): View
    {
        $category = QuestionCategory::tryFrom((string) request()->query('category'));
        $search = (string) request()->query('q');
        $taken = $package->questions()->pluck('questions.id');

        return view('admin.packages.builder', [
            'site' => $content->site(),
            'title' => 'Susun '.$package->title,
            'package' => $package,
            'report' => PackageComposition::report($package),
            'complete' => PackageComposition::isComplete($package),
            'shortfall' => PackageComposition::shortfallMessage($package),
            'locked' => $package->isLocked(),
            'items' => $package->questions()->with('options')->get(),
            'candidates' => Question::query()
                ->active()
                ->with('options')
                ->when($category !== null, fn ($query) => $query->category($category))
                ->when($search !== '', fn ($query) => $query->where('stem', 'like', '%'.$search.'%'))
                ->whereNotIn('questions.id', $taken)
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'category' => $category,
            'search' => $search,
        ]);
    }

    public function attach(Request $request, ExamPackage $package): RedirectResponse
    {
        $this->refuseIfLocked($package);

        $question = Question::query()->findOrFail($request->integer('question_id'));

        if (! $package->questions()->whereKey($question->id)->exists()) {
            $package->addQuestion($question);
        }

        return back()->with('status', 'Soal ditambahkan ke paket.');
    }

    public function detach(ExamPackage $package, Question $question): RedirectResponse
    {
        $this->refuseIfLocked($package);

        $package->questions()->detach($question->id);

        return back()->with('status', 'Soal dikeluarkan dari paket.');
    }

    public function move(Request $request, ExamPackage $package, Question $question): RedirectResponse
    {
        $this->refuseIfLocked($package);

        $direction = $request->string('direction')->toString() === 'up' ? -1 : 1;

        DB::transaction(function () use ($package, $question, $direction): void {
            $ordered = $package->questions()->get()->all();
            $index = null;

            foreach ($ordered as $position => $row) {
                if ($row->id === $question->id) {
                    $index = $position;
                    break;
                }
            }

            $target = $index === null ? null : $index + $direction;

            if ($index === null || $target === null || $target < 0 || $target >= count($ordered)) {
                return;
            }

            // A straight swap collides with the unique key on (package, position),
            // so every row is pushed out of range first and renumbered after.
            $package->questions()->newPivotStatement()
                ->where('exam_package_id', $package->id)
                ->update(['position' => DB::raw('position + 1000')]);

            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

            foreach ($ordered as $offset => $row) {
                $package->questions()->updateExistingPivot($row->id, ['position' => $offset + 1]);
            }
        });

        return back();
    }

    public function publish(ExamPackage $package): RedirectResponse
    {
        // Refused in the controller, not only by disabling a button: a half-built
        // package must never be reachable by a participant.
        if (! PackageComposition::isComplete($package)) {
            throw ValidationException::withMessages([
                'package' => 'Paket belum genap. '.PackageComposition::shortfallMessage($package),
            ]);
        }

        $package->forceFill(['status' => ExamPackage::STATUS_PUBLISHED])->save();

        return back()->with('status', 'Paket dibuka. Peserta bisa mengerjakannya sekarang.');
    }

    public function unpublish(ExamPackage $package): RedirectResponse
    {
        $package->forceFill(['status' => ExamPackage::STATUS_DRAFT])->save();

        return back()->with('status', 'Paket ditutup lagi.');
    }

    private function refuseIfLocked(ExamPackage $package): void
    {
        if ($package->isLocked()) {
            throw ValidationException::withMessages([
                'package' => 'Komposisi paket ini dikunci karena sudah ada yang mengerjakannya. '
                    .'Teks soal tetap bisa diubah dari bank soal; untuk komposisi berbeda, buat paket baru.',
            ]);
        }
    }
}
```

- [ ] **Step 4: Write the three views**

`resources/views/admin/packages/index.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-heading text-h2 font-bold text-ink">Paket tryout</h1>
        <a href="{{ route('admin.packages.create') }}" class="inline-flex items-center gap-2 rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Paket baru
        </a>
    </div>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($packages as $row)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $row['package']->title }}</strong>
                        <p class="text-small text-muted">
                            {{ $row['package']->questions_count }} soal ·
                            {{ $row['package']->duration_minutes }} menit ·
                            {{ $row['package']->attempts_count }} percobaan
                        </p>
                        <p class="mt-1 text-small {{ $row['complete'] ? 'text-success' : 'text-danger' }}">
                            {{ $row['complete'] ? 'Komposisi genap' : $row['shortfall'] }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-card px-3 py-1.5 text-caption font-semibold {{ $row['package']->isPublished() ? 'bg-success/10 text-success' : 'bg-surface text-muted' }}">
                            {{ $row['package']->isPublished() ? 'published' : 'draft' }}
                        </span>
                        <a href="{{ route('admin.packages.builder', $row['package']) }}" class="rounded-card border border-line px-4 py-2.5 font-heading text-small font-bold text-ink">
                            Susun
                        </a>
                        <a href="{{ route('admin.packages.edit', $row['package']) }}" class="rounded-card border border-line px-4 py-2.5 font-heading text-small font-bold text-ink">
                            Ubah
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">Belum ada paket tryout.</p>
        @endforelse
    </div>
</x-layouts.admin>
```

`resources/views/admin/packages/create.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">{{ isset($package) ? 'Ubah paket' : 'Paket baru' }}</h1>

    @if ($errors->any())
        <ul class="mt-5 space-y-1 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    <form method="POST"
        action="{{ isset($package) ? route('admin.packages.update', $package) : route('admin.packages.store') }}"
        class="mt-6 grid max-w-[720px] gap-4 rounded-card border border-line bg-white p-5 shadow-card">
        @csrf
        @if (isset($package))
            @method('PUT')
        @endif

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Judul</span>
            <input type="text" name="title" required value="{{ old('title', $package->title ?? '') }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Slug</span>
            <span class="mb-1.5 block text-caption text-muted">Dipakai di URL. Kosongkan untuk dibuat dari judul.</span>
            <input type="text" name="slug" value="{{ old('slug', $package->slug ?? '') }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Deskripsi</span>
            <textarea name="description" rows="2" class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">{{ old('description', $package->description ?? '') }}</textarea>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-small font-semibold text-ink">Durasi (menit)</span>
            <input type="number" name="duration_minutes" min="10" max="300" required
                value="{{ old('duration_minutes', $package->duration_minutes ?? config('cpns.duration_minutes')) }}"
                class="w-full rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
        </label>

        <div class="flex gap-3">
            <button type="submit" class="rounded-card bg-brand px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-brand-dark">Simpan</button>
            <a href="{{ route('admin.packages.index') }}" class="rounded-card border border-line px-5 py-3 font-heading text-small font-bold text-ink">Batal</a>
        </div>
    </form>
</x-layouts.admin>
```

`resources/views/admin/packages/builder.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">{{ $package->title }}</h1>
    <p class="mt-2 text-body text-muted">
        {{ $package->duration_minutes }} menit · maks {{ config('cpns.total_max') }} ·
        {{ $package->questions()->count() }} dari {{ config('cpns.total_items') }} soal
    </p>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <p class="mt-5 rounded-card border border-danger/30 bg-danger/5 px-4 py-3 text-small text-danger">{{ $errors->first() }}</p>
    @endif

    @if ($locked)
        <p class="mt-5 rounded-card border border-warning/30 bg-warning/5 px-4 py-3 text-small text-warning">
            Komposisi paket ini dikunci karena sudah ada percobaan. Teks soal dan pembahasannya tetap bisa
            diubah dari bank soal; untuk susunan yang berbeda, buat paket baru.
        </p>
    @endif

    {{-- The shortfall bar: what is missing, per subtest, at a glance. --}}
    <div class="mt-6 grid gap-3 lg:grid-cols-3">
        @foreach ($report as $subtest)
            <div class="rounded-card border border-line bg-white p-4 shadow-card">
                <div class="flex items-baseline justify-between gap-2">
                    <strong class="font-heading text-body font-bold text-ink">{{ $subtest['label'] }}</strong>
                    <span class="font-heading text-body font-bold {{ $subtest['complete'] ? 'text-success' : 'text-danger' }}">
                        {{ $subtest['present'] }} / {{ $subtest['required'] }}
                    </span>
                </div>
                <div class="mt-3 h-2 rounded-full bg-surface">
                    <div class="h-2 rounded-full {{ $subtest['complete'] ? 'bg-success' : 'bg-danger' }}"
                        style="width: {{ min(100, (int) round($subtest['present'] / max(1, $subtest['required']) * 100)) }}%"></div>
                </div>
                <p class="mt-2 text-caption text-muted">
                    {{ $subtest['complete'] ? 'genap' : 'kurang '.$subtest['missing'].' soal lagi' }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        @if ($package->isPublished())
            <form method="POST" action="{{ route('admin.packages.unpublish', $package) }}">
                @csrf
                <button type="submit" class="rounded-card border border-line bg-white px-5 py-3 font-heading text-small font-bold text-ink">Tutup paket</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.packages.publish', $package) }}">
                @csrf
                <button type="submit" @disabled(! $complete)
                    class="rounded-card bg-accent px-5 py-3 font-heading text-small font-bold text-white transition hover:bg-accent-dark disabled:opacity-40">
                    Buka untuk peserta
                </button>
            </form>
            @unless ($complete)
                <p class="self-center text-small text-muted">
                    Tombol ini aktif setelah komposisinya genap. {{ $shortfall }}
                </p>
            @endunless
        @endif
    </div>

    <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Isi paket</h2>
    <div class="mt-4 space-y-2">
        @forelse ($items as $index => $item)
            <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-white p-4 shadow-card">
                <span class="w-8 font-heading text-small font-bold text-muted">{{ $index + 1 }}</span>
                <span class="min-w-0 flex-1 text-small text-ink">{{ \Illuminate\Support\Str::limit($item->stem, 120) }}</span>
                <span class="text-caption text-muted">{{ $item->category->label() }} · {{ $item->subcategoryLabel() }}</span>

                @unless ($locked)
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('admin.packages.move', [$package, $item]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded-card border border-line px-2.5 py-1.5 text-small text-muted" aria-label="Naikkan">&uarr;</button>
                        </form>
                        <form method="POST" action="{{ route('admin.packages.move', [$package, $item]) }}">
                            @csrf
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded-card border border-line px-2.5 py-1.5 text-small text-muted" aria-label="Turunkan">&darr;</button>
                        </form>
                        <form method="POST" action="{{ route('admin.packages.detach', [$package, $item]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-card border border-line px-3 py-1.5 text-small text-muted transition hover:border-danger hover:text-danger">Keluarkan</button>
                        </form>
                    </div>
                @endunless
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">Paket ini masih kosong.</p>
        @endforelse
    </div>

    @unless ($locked)
        <h2 class="mt-10 font-heading text-h3 font-bold text-ink">Tambah dari bank soal</h2>

        <form method="GET" action="{{ route('admin.packages.builder', $package) }}" class="mt-4 grid gap-3 lg:grid-cols-[200px_minmax(0,1fr)_auto]">
            <select name="category" class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
                <option value="">Semua subtes</option>
                @foreach (\App\Enums\QuestionCategory::cases() as $option)
                    <option value="{{ $option->value }}" @selected($category === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari teks soal"
                class="rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
            <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">Cari</button>
        </form>

        <div class="mt-4 space-y-2">
            @forelse ($candidates as $candidate)
                <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-white p-4 shadow-card">
                    <span class="min-w-0 flex-1 text-small text-ink">{{ \Illuminate\Support\Str::limit($candidate->stem, 120) }}</span>
                    <span class="text-caption text-muted">{{ $candidate->category->label() }} · {{ $candidate->subcategoryLabel() }}</span>
                    <form method="POST" action="{{ route('admin.packages.attach', $package) }}">
                        @csrf
                        <input type="hidden" name="question_id" value="{{ $candidate->id }}">
                        <button type="submit" class="rounded-card bg-brand px-4 py-2 font-heading text-small font-bold text-white transition hover:bg-brand-dark">Tambah</button>
                    </form>
                </div>
            @empty
                <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">
                    Tidak ada soal yang cocok, atau semua yang cocok sudah masuk paket ini.
                </p>
            @endforelse
        </div>

        <div class="mt-4">{{ $candidates->links() }}</div>
    @endunless
</x-layouts.admin>
```

- [ ] **Step 5: Add the routes and update the dashboard button**

In `routes/admin.php`:

```php
Route::get('/paket', [PackageController::class, 'index'])->name('packages.index');
Route::get('/paket/baru', [PackageController::class, 'create'])->name('packages.create');
Route::post('/paket', [PackageController::class, 'store'])->name('packages.store');
Route::get('/paket/{package}/ubah', [PackageController::class, 'edit'])->name('packages.edit');
Route::put('/paket/{package}', [PackageController::class, 'update'])->name('packages.update');
Route::get('/paket/{package}/susun', [PackageBuilderController::class, 'show'])->name('packages.builder');
Route::post('/paket/{package}/soal', [PackageBuilderController::class, 'attach'])->name('packages.attach');
Route::delete('/paket/{package}/soal/{question}', [PackageBuilderController::class, 'detach'])->name('packages.detach');
Route::post('/paket/{package}/soal/{question}/pindah', [PackageBuilderController::class, 'move'])->name('packages.move');
Route::post('/paket/{package}/buka', [PackageBuilderController::class, 'publish'])->name('packages.publish');
Route::post('/paket/{package}/tutup', [PackageBuilderController::class, 'unpublish'])->name('packages.unpublish');
```

In `resources/views/admin/dashboard.blade.php`, change the shortfall card's button from `route('admin.packages.index')` to:

```blade
                    <a href="{{ route('admin.packages.builder', $row['package']) }}"
```

- [ ] **Step 6: Write the failing test**

`tests/Feature/Admin/PackageAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\Question;
use App\Models\User;
use App\Support\PackageComposition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PackageAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_creating_a_package_lands_on_the_builder(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/paket', [
                'title' => 'Tryout SKD Perdana',
                'slug' => '',
                'description' => 'Latihan pertama',
                'duration_minutes' => 100,
            ])
            ->assertRedirect(route('admin.packages.builder', ExamPackage::query()->firstOrFail()));

        $package = ExamPackage::query()->firstOrFail();

        $this->assertSame('tryout-skd-perdana', $package->slug);
        $this->assertSame(ExamPackage::STATUS_DRAFT, $package->status);
    }

    public function test_adding_and_removing_items_keeps_the_numbering_gapless(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(3)->create();

        foreach ($questions as $question) {
            $this->actingAs($this->admin())
                ->post('/admin/paket/'.$package->id.'/soal', ['question_id' => $question->id]);
        }

        $this->assertSame([1, 2, 3], $package->questions()->get()->pluck('pivot.position')->all());

        $this->actingAs($this->admin())
            ->delete('/admin/paket/'.$package->id.'/soal/'.$questions[1]->id);

        $this->assertSame(2, $package->questions()->count());
    }

    public function test_adding_the_same_item_twice_changes_nothing(): void
    {
        $package = ExamPackage::factory()->create();
        $question = Question::factory()->twk()->create();

        $this->actingAs($this->admin())->post('/admin/paket/'.$package->id.'/soal', ['question_id' => $question->id]);
        $this->actingAs($this->admin())->post('/admin/paket/'.$package->id.'/soal', ['question_id' => $question->id]);

        $this->assertSame(1, $package->questions()->count());
    }

    public function test_reordering_swaps_two_items_without_tripping_the_unique_position(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(3)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $this->actingAs($this->admin())
            ->post('/admin/paket/'.$package->id.'/soal/'.$questions[2]->id.'/pindah', ['direction' => 'up'])
            ->assertRedirect();

        $ordered = $package->questions()->get();

        $this->assertSame([$questions[0]->id, $questions[2]->id, $questions[1]->id], $ordered->pluck('id')->all());
        $this->assertSame([1, 2, 3], $ordered->pluck('pivot.position')->all());
    }

    public function test_moving_the_first_item_up_is_a_no_op(): void
    {
        $package = ExamPackage::factory()->create();
        $questions = Question::factory()->twk()->count(2)->create();

        foreach ($questions as $question) {
            $package->addQuestion($question);
        }

        $this->actingAs($this->admin())
            ->post('/admin/paket/'.$package->id.'/soal/'.$questions[0]->id.'/pindah', ['direction' => 'up']);

        $this->assertSame($questions->pluck('id')->all(), $package->questions()->get()->pluck('id')->all());
    }

    public function test_publishing_is_refused_until_the_composition_is_exact(): void
    {
        $package = ExamPackage::factory()->create();
        $package->addQuestion(Question::factory()->twk()->create());

        $this->actingAs($this->admin())
            ->post('/admin/paket/'.$package->id.'/buka')
            ->assertSessionHasErrors('package');

        $this->assertSame(ExamPackage::STATUS_DRAFT, $package->refresh()->status);
    }

    public function test_publishing_is_refused_at_a_wrong_category_split(): void
    {
        $package = ExamPackage::factory()->create();

        foreach (['twk' => 31, 'tiu' => 34, 'tkp' => 45] as $category => $count) {
            $enum = \App\Enums\QuestionCategory::from($category);
            for ($i = 0; $i < $count; $i++) {
                $package->addQuestion(Question::factory()->category($enum)->create());
            }
        }

        $this->assertSame(110, $package->questions()->count());
        $this->assertFalse(PackageComposition::isComplete($package));

        $this->actingAs($this->admin())
            ->post('/admin/paket/'.$package->id.'/buka')
            ->assertSessionHasErrors('package');
    }

    public function test_a_complete_package_publishes_and_indexes(): void
    {
        $package = ExamPackage::factory()->complete()->create();

        $this->actingAs($this->admin())->post('/admin/paket/'.$package->id.'/buka')->assertRedirect();

        $this->assertTrue($package->refresh()->isPublished());
    }

    public function test_the_composition_locks_once_someone_has_sat_the_package(): void
    {
        $user = User::factory()->create();
        $package = ExamPackage::factory()->complete()->published()->create();
        $question = $package->questions()->first();
        $spare = Question::factory()->twk()->create();

        ExamAttempt::factory()->forUserAndPackage($user, $package)->submitted()->create();

        $this->assertTrue($package->refresh()->isLocked());

        $this->actingAs($this->admin())
            ->post('/admin/paket/'.$package->id.'/soal', ['question_id' => $spare->id])
            ->assertSessionHasErrors('package');

        $this->actingAs($this->admin())
            ->delete('/admin/paket/'.$package->id.'/soal/'.$question->id)
            ->assertSessionHasErrors('package');

        $this->assertSame(110, $package->questions()->count());

        // Editing the item itself still works: only the composition is frozen.
        $this->actingAs($this->admin())->put('/admin/soal/'.$question->id, [
            'category' => $question->category->value,
            'subcategory' => $question->subcategory,
            'stem' => 'Teks soal yang diperbaiki pada paket yang sudah dikunci',
            'explanation' => null,
            'is_active' => '1',
            'key' => $question->correctOption()->label,
            'options' => collect(['A', 'B', 'C', 'D', 'E'])
                ->mapWithKeys(fn (string $letter): array => [$letter => ['body' => 'Opsi '.$letter]])
                ->all(),
        ])->assertRedirect();

        $this->assertSame('Teks soal yang diperbaiki pada paket yang sudah dikunci', $question->refresh()->stem);
    }
}
```

- [ ] **Step 7: Run the test to verify it fails**

Run: `php artisan test --filter=PackageAdminTest`
Expected: FAIL — `Route [admin.packages.index] not defined.`

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=PackageAdminTest`
Expected: PASS, 9 tests. If a move test fails with a unique-constraint error, the pivot bump in `move()` is not running inside the same transaction as the renumbering.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Admin/BuildPackageRequest.php app/Http/Controllers/Admin/PackageController.php app/Http/Controllers/Admin/PackageBuilderController.php resources/views/admin/packages resources/views/admin/dashboard.blade.php routes/admin.php tests/Feature/Admin/PackageAdminTest.php
git commit -m "feat(admin): the package builder with composition lock and server-side publish gate"
```

---

### Task 16: Participants screen — fix an email, reset a password

**Files:**
- Create: `app/Http/Requests/Admin/UpdateUserRequest.php`
- Create: `app/Http/Controllers/Admin/UserController.php`
- Create: `resources/views/admin/users/index.blade.php`
- Modify: `routes/admin.php`
- Test: `tests/Feature/Admin/UserAdminTest.php`

**Interfaces:**
- Consumes: `User`, `UserRole`, `config('cpns.password.min_length')`.
- Produces: routes `admin.users.index|update|reset-password`; the temporary password is flashed as `temporary_password` and shown once.

- [ ] **Step 1: Write the request and controller**

`app/Http/Requests/Admin/UpdateUserRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // With no mailer, a mistyped address cannot be proven wrong — so the
            // admin has to be able to correct it. That is the whole mitigation.
            'email' => [
                'required', 'string', 'email', 'max:190',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
```

`app/Http/Controllers/Admin/UserController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Support\ContentRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class UserController extends Controller
{
    public function index(Request $request, ContentRepository $content): View
    {
        $search = (string) $request->query('q');

        $users = User::query()
            ->withCount('attempts')
            ->when($search !== '', fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('participant_number', 'like', '%'.$search.'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'site' => $content->site(),
            'title' => 'Pengguna',
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back()->with('status', 'Data pengguna diperbarui.');
    }

    /**
     * The reset that exists because email does not. The temporary password is shown
     * once on screen — not emailed, not written to a log — and the account is held
     * at the change-password form until it is replaced. Without that flag a
     * temporary password becomes permanent, which is the classic hole in an
     * application with no mailer.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $password = Str::password(10, symbols: false);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        return back()->with('temporary_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ]);
    }
}
```

`Str::password(10, symbols: false)` produces letters, numbers and dashes; the password rule in
this app wants letters and numbers, so pass `letters: true, numbers: true, symbols: false`.
Use `Str::password(10, symbols: false)` and confirm the generated string satisfies
`Password::min(8)->letters()->numbers()` in the test — if it does not, generate with
`Str::random(6).random_int(1000, 9999)` instead.

- [ ] **Step 2: Write the view**

`resources/views/admin/users/index.blade.php`:

```blade
<x-layouts.admin :title="$title" :site="$site">
    <h1 class="font-heading text-h2 font-bold text-ink">Pengguna</h1>
    <p class="mt-2 text-body text-muted">{{ $users->total() }} akun.</p>

    @if (session('status'))
        <p class="mt-5 rounded-card border border-success/30 bg-success/5 px-4 py-3 text-small text-success">{{ session('status') }}</p>
    @endif

    @if (session('temporary_password'))
        @php($reset = session('temporary_password'))
        <div class="mt-5 rounded-card border border-warning/40 bg-warning/5 px-4 py-4 text-small text-warning">
            <p class="font-semibold">Password sementara untuk {{ $reset['name'] }} ({{ $reset['email'] }}):</p>
            <p class="mt-2 font-mono text-body font-bold">{{ $reset['password'] }}</p>
            <p class="mt-2">
                Ini hanya tampil sekali dan tidak dikirim ke mana-mana. Berikan ke pemiliknya langsung; akun ini
                wajib mengganti password saat pertama masuk.
            </p>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-6 flex gap-3">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama, email, atau nomor peserta"
            class="w-full max-w-[420px] rounded-card border border-line bg-white px-3.5 py-2.5 text-small text-ink">
        <button type="submit" class="rounded-card border border-line bg-white px-4 py-2.5 font-heading text-small font-bold text-ink">Cari</button>
    </form>

    <div class="mt-5 space-y-3">
        @forelse ($users as $user)
            <div class="rounded-card border border-line bg-white p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <strong class="font-heading text-body font-bold text-ink">{{ $user->name }}</strong>
                        <p class="text-small text-muted">
                            {{ $user->email }} · {{ $user->participant_number ?? 'tanpa nomor' }} ·
                            {{ $user->attempts_count }} percobaan
                        </p>
                        <p class="mt-1 text-caption">
                            <span class="{{ $user->isAdmin() ? 'text-brand' : 'text-muted' }}">{{ $user->role->label() }}</span>
                            @if ($user->must_change_password)
                                <span class="text-warning">· menunggu ganti password</span>
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                        onsubmit="return confirm('Buat password sementara untuk {{ $user->name }}?')">
                        @csrf
                        <button type="submit" class="rounded-card border border-line px-4 py-2.5 font-heading text-small font-bold text-ink transition hover:border-warning hover:text-warning">
                            Reset password
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_160px_auto]">
                    @csrf
                    @method('PUT')

                    <input type="text" name="name" value="{{ $user->name }}" required
                        class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                    <input type="email" name="email" value="{{ $user->email }}" required
                        class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                    <select name="role" class="rounded-card border border-line px-3.5 py-2.5 text-small text-ink">
                        @foreach (\App\Enums\UserRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-card bg-brand px-4 py-2.5 font-heading text-small font-bold text-white transition hover:bg-brand-dark">
                        Simpan
                    </button>
                </form>
            </div>
        @empty
            <p class="rounded-card border border-line bg-white p-5 text-small text-muted shadow-card">Tidak ada akun yang cocok.</p>
        @endforelse
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
</x-layouts.admin>
```

- [ ] **Step 3: Add the routes**

In `routes/admin.php`:

```php
Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
Route::post('/pengguna/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
```

- [ ] **Step 4: Write the failing test**

`tests/Feature/Admin/UserAdminTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_peserta_cannot_open_the_participants_screen(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/pengguna')->assertForbidden();
    }

    public function test_the_screen_searches_by_name_email_and_participant_number(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.test']);
        $user->assignParticipantNumber();

        $response = $this->actingAs($this->admin())->get('/admin/pengguna?q=budi@example.test');

        $response->assertOk();
        $response->assertSee('budi@example.test');
    }

    public function test_an_admin_can_fix_a_mistyped_email(): void
    {
        $user = User::factory()->create(['email' => 'salah@example.tets']);

        $this->actingAs($this->admin())->put('/admin/pengguna/'.$user->id, [
            'name' => $user->name,
            'email' => 'benar@example.test',
            'role' => UserRole::Peserta->value,
        ])->assertRedirect();

        $this->assertSame('benar@example.test', $user->refresh()->email);
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test']);
        $user = User::factory()->create();

        $this->actingAs($this->admin())->put('/admin/pengguna/'.$user->id, [
            'name' => $user->name,
            'email' => 'dipakai@example.test',
            'role' => UserRole::Peserta->value,
        ])->assertSessionHasErrors('email');
    }

    public function test_resetting_a_password_shows_one_temporary_password_and_forces_a_change(): void
    {
        $user = User::factory()->create(['password' => 'lupa12345']);

        $response = $this->actingAs($this->admin())
            ->post('/admin/pengguna/'.$user->id.'/reset-password')
            ->assertRedirect();

        $temporary = session('temporary_password');

        $this->assertIsArray($temporary);
        $this->assertSame($user->email, $temporary['email']);
        $this->assertNotSame('lupa12345', $temporary['password']);

        $user->refresh();

        $this->assertTrue(Hash::check($temporary['password'], $user->password));
        $this->assertTrue($user->must_change_password);

        // The temporary password cannot be used for anything until it is replaced.
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.edit'));
    }

    public function test_an_admin_can_promote_and_demote_a_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->put('/admin/pengguna/'.$user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => UserRole::Admin->value,
        ]);

        $this->assertTrue($user->refresh()->isAdmin());
    }
}
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `php artisan test --filter=UserAdminTest`
Expected: FAIL — `Route [admin.users.index] not defined.`

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=UserAdminTest`
Expected: PASS, 6 tests. If `test_resetting_a_password...` fails on the password rule, switch the generator in the controller to `Str::random(6).random_int(1000, 9999)`.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Admin/UpdateUserRequest.php app/Http/Controllers/Admin/UserController.php resources/views/admin/users routes/admin.php tests/Feature/Admin/UserAdminTest.php
git commit -m "feat(admin): the participants screen with email fixes and panel-driven password resets"
```

---

### Task 17: The optional sweep, the docs, and the deploy

**Files:**
- Create: `app/Console/Commands/ExpireAttemptsCommand.php`
- Create: `tools/exam-smoke.mjs`
- Modify: `deploy/host-cpns.sh` (the commented schedule line), `docs/WORKING-NOTES.md`, `README.md`
- Test: `tests/Feature/Exam/ExpireCommandTest.php`

**Interfaces:**
- Consumes: `AttemptManager::expireAllOverdue()`, the deploy script's existing structure.
- Produces: `php artisan cpns:expire-attempts`; a browser smoke script that drives the exam end to end.

- [ ] **Step 1: Write the sweep command**

`app/Console/Commands/ExpireAttemptsCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Exam\AttemptManager;
use Illuminate\Console\Command;

/**
 * A complement, not a dependency. Expiry is already evaluated on the read path, so
 * nothing in the application needs this to be correct — it exists so that an
 * attempt abandoned forever does not sit in the database as "in progress" and
 * distort the admin dashboard's attempt count.
 */
final class ExpireAttemptsCommand extends Command
{
    protected $signature = 'cpns:expire-attempts';

    protected $description = 'Grade and close attempts whose time ran out';

    public function handle(AttemptManager $manager): int
    {
        $closed = $manager->expireAllOverdue();

        $this->info($closed === 0
            ? 'Tidak ada percobaan yang perlu ditutup.'
            : "{$closed} percobaan ditutup dan dinilai.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Write the failing test**

`tests/Feature/Exam/ExpireCommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use App\Models\ExamPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SitsExam;
use Tests\TestCase;

final class ExpireCommandTest extends TestCase
{
    use RefreshDatabase;
    use SitsExam;

    public function test_it_closes_every_overdue_attempt_without_touching_open_ones(): void
    {
        $package = ExamPackage::factory()->complete()->create();
        $abandoned = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->overdue()
            ->create();
        $running = ExamAttempt::factory()
            ->forUserAndPackage(User::factory()->create(), $package)
            ->create();

        $this->answerCorrectly($abandoned, 'twk', 30);

        $this->artisan('cpns:expire-attempts')->assertSuccessful();

        $this->assertSame(AttemptStatus::Expired, $abandoned->refresh()->status);
        $this->assertSame(150, $abandoned->total_score);
        $this->assertSame(AttemptStatus::InProgress, $running->refresh()->status);
    }

    public function test_it_says_so_when_there_is_nothing_to_do(): void
    {
        $this->artisan('cpns:expire-attempts')
            ->expectsOutput('Tidak ada percobaan yang perlu ditutup.')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 3: Run the test to verify it fails, then passes**

Run: `php artisan test --filter=ExpireCommandTest`
Expected first: FAIL — command not found. After writing the command: PASS, 2 tests.

- [ ] **Step 4: Write the browser smoke script**

`tools/exam-smoke.mjs`:

```js
// Drives a whole CPNS sitting in a real browser: register, start, answer, skip, flag,
// jump with the navigator, reload and resume, finish, and read the analysis page.
//
// Separate from tools/live-smoke.mjs on purpose: that one checks the marketing page
// against the deployed site without touching the database, while this one needs an
// account and writes a full attempt. Run it against the loopback port first, then
// against the public URL.
//
// Usage: node tools/exam-smoke.mjs http://127.0.0.1:8000
import { chromium } from 'playwright-core';

const BASE = process.argv[2] ?? 'http://127.0.0.1:8000';
const CHROME = process.env.CHROME_PATH
  ?? `${process.env.HOME}/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome`;

const results = [];

const check = (name, ok, detail = '') => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'ok  ' : 'FAIL'} ${name}${detail ? ` — ${detail}` : ''}`);
};

const email = `smoke-${Date.now()}@example.test`;

const browser = await chromium.launch({ executablePath: CHROME, args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
const consoleErrors = [];

page.on('console', (message) => {
  if (message.type() === 'error') consoleErrors.push(message.text());
});
page.on('pageerror', (error) => consoleErrors.push(String(error)));

try {
  // Register.
  await page.goto(`${BASE}/daftar`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="name"]', 'Peserta Smoke');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'rahasia123');
  await page.fill('input[name="password_confirmation"]', 'rahasia123');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard');
  check('register lands on the dashboard', page.url().includes('/dashboard'));

  await page.goto(`${BASE}/tryout`, { waitUntil: 'domcontentloaded' });

  const starters = page.locator('form[action$="/mulai"] button[type="submit"]');

  if ((await starters.count()) === 0) {
    check('a published package exists to sit', false, 'create and publish a package first');
  } else {
    await starters.first().click();
    await page.waitForURL('**/tryout/attempt/**');

    const attemptUrl = page.url();
    check('starting opens the exam room', /\/tryout\/attempt\/\d+$/.test(attemptUrl));

    const total = Number(await page.getAttribute('[data-exam]', 'data-total'));
    check('the room states the item count from config', total === 110, `total=${total}`);

    const clockBefore = await page.textContent('[data-exam-clock]');
    await page.waitForTimeout(2200);
    check('the clock counts down', clockBefore !== (await page.textContent('[data-exam-clock]')));

    await page.locator('[data-exam-item]:not([hidden]) [data-exam-option]').first().click();
    await page.waitForFunction(() => document.querySelector('[data-exam-cell]')?.dataset.status === 'answered');
    check('answering turns the first navigator cell green', true);
    check('the answered counter follows', (await page.textContent('[data-exam-answered]')).trim() === '1');

    await page.locator('[data-exam-item]:not([hidden]) [data-exam-flag]').click();
    await page.waitForFunction(() => document.querySelector('[data-exam-cell]')?.dataset.flag === 'true');
    check('flagging marks the cell', true);

    await page.click('[data-exam-skip]');
    const secondStatus = await page.locator('[data-exam-cell]').nth(1).getAttribute('data-status');
    check('skipping leaves the item unanswered', secondStatus === 'blank', `status=${secondStatus}`);

    await page.reload({ waitUntil: 'domcontentloaded' });
    check('the answer survives a reload', (await page.locator('[data-exam-cell]').first().getAttribute('data-status')) === 'answered');

    // Answer every remaining item through the navigator. Deliberately slow and
    // explicit: a smoke test that hides its actions is worse than a slow one.
    for (let index = 0; index < total; index++) {
      await page.locator('[data-exam-cell]').nth(index).click();

      const current = page.locator('[data-exam-item]:not([hidden])');

      if ((await current.locator('[data-exam-option][data-selected="true"]').count()) === 0) {
        await current.locator('[data-exam-option]').first().click();
        await page.waitForTimeout(120);
      }
    }

    check('all 110 items are answered', (await page.textContent('[data-exam-answered]')).trim() === '110');

    // On a phone the finish button lives inside the bottom sheet, on purpose.
    await page.click('[data-exam-sheet-toggle]');
    await page.locator('[data-exam-sheet] [data-exam-finish-button]').click();
    await page.waitForURL('**/hasil/**', { timeout: 30000 });
    check('finishing lands on the analysis page', page.url().includes('/hasil/'));

    await page.waitForSelector('text=Pembahasan');
    check('the analysis page renders its review', true);

    await page.goto(`${attemptUrl.replace('/tryout/attempt/', '/hasil/')}?filter=wrong`, { waitUntil: 'domcontentloaded' });
    check('the review filter is a real URL', page.url().includes('filter=wrong'));

    check('no console errors during the whole sitting', consoleErrors.length === 0, consoleErrors.slice(0, 3).join(' | '));
  }
} finally {
  await browser.close();
}

const failed = results.filter((result) => !result.ok);
console.log(`\n${results.length - failed.length} ok, ${failed.length} failed`);
process.exit(failed.length === 0 ? 0 : 1);
```

Note for the implementer: this script writes a real attempt into whatever database
`BASE` points at. Run it against the loopback port before running it against the public URL,
and delete the smoke accounts afterwards if you would rather not keep them. The loop that
answers every remaining item is deliberately slow and explicit rather than clever — a smoke
test that hides its actions is worse than a slow one.

- [ ] **Step 5: Note the optional schedule in the deploy script**

In `deploy/host-cpns.sh`, near the deploy steps, add a commented block so the choice is
visible where the deploy happens and nobody has to guess that it exists:

```bash
# Expiry is evaluated on the read path, so the application is correct without any
# scheduler. Uncomment the line below (and the schedule:run entry it installs) only if
# you want abandoned attempts swept even when nobody opens the app.
#   sudo -u ahlfs -H bash -lc "cd $PROJECT_ROOT && php artisan cpns:expire-attempts"
```

- [ ] **Step 6: Update the project notes and README**

Append to `docs/WORKING-NOTES.md` a section titled `## CPNS tryout module` covering, in prose:
where the decisions came from (`docs/SPEC-CPNS-TRYOUT.md`, `docs/plans/2026-11-15-cpns-tryout-module.md`),
that the exam clock is the server's and expiry rides the read path, that scores are frozen at
submit while item text stays live, that package composition locks at the first attempt, that
imports are all-or-nothing, that the first admin comes from `php artisan cpns:admin`, and that
`password_reset_tokens` is intentionally unused because email is dead on this host.

In `README.md`, add the module to the feature list and add one line to the local-setup section:
`php artisan cpns:admin you@example.com` to get into `/admin`.

- [ ] **Step 7: Run everything and format**

```bash
php artisan test
vendor/bin/pint
npm run build
```

Expected: the whole suite passes (the marketing tests from before this module included), Pint
reports no changes, and Vite builds without warnings.

- [ ] **Step 8: Deploy and verify over the public URL**

```bash
sudo bash deploy/host-cpns.sh enable
node tools/exam-smoke.mjs https://cpns.buatjalan.xyz
```

Expected: the deploy script reports its usual `ok`/`failed` tally with zero failures, Caddy
reloads without disturbing its eleven neighbours, and the smoke script prints `14 ok, 0 failed`
(more if extra checks were added).

- [ ] **Step 9: The one check no script can make**

Sit a full 110-item attempt on a phone, on the real deployment, in one go: the top bar's
countdown stays reachable, the bottom sheet opens and closes, touch targets do not mis-hit,
the wake lock keeps the screen alive, and finishing takes you to the analysis. This is the
last acceptance item in `docs/SPEC-CPNS-TRYOUT.md` §12 and it stays manual on purpose — 100
minutes of use on a small screen is the thing being tested, not the code path.

- [ ] **Step 10: Commit**

```bash
git add app/Console/Commands/ExpireAttemptsCommand.php tests/Feature/Exam/ExpireCommandTest.php tools/exam-smoke.mjs deploy/host-cpns.sh docs/WORKING-NOTES.md README.md
git commit -m "feat(exam): the optional expiry sweep, the browser smoke script and the project notes"
```

---

## Notes for the executor

Two places where the plan is deliberately explicit about its own deviation:

1. **Task 11 changes two assertions written in Tasks 9 and 10.** They expected `tryout.index`
   because `hasil.show` did not exist yet, and a task should not point a redirect at a route
   that is not there. Both are named in Task 11 Step 6 rather than left to be discovered.
2. **Task 17 adds `tools/exam-smoke.mjs` instead of extending `tools/live-smoke.mjs`**, which
   is what `docs/SPEC-CPNS-TRYOUT.md` §12 says. The spec's intent is a browser check against
   the deployed site; the marketing smoke script is built to run without an account and
   without writing to the database, and the exam flow needs both. Splitting keeps each script
   honest about what it touches.


