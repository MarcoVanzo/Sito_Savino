<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FotoProdottoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le foto da fotocamera superano i 10 MB di serie della media library:
     * la scheda prodotto andava in 500 al salvataggio, spesso alla seconda
     * foto (02/10/2026).
     */
    #[Test]
    public function due_foto_oltre_i_dieci_mega_si_salvano(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($user);

        $prodotto = Product::factory()->create([
            'product_category_id' => ProductCategory::factory()->create()->id,
        ]);

        Livewire::test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('data.images.prima', $this->fotoDa(12))
            ->set('data.images.seconda', $this->fotoDa(12))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(2, $prodotto->fresh()->getMedia('images'));
    }

    /** Dal pannello l'originale arriva alleggerito (FotoAlleggerita). */
    #[Test]
    public function la_foto_da_fotocamera_si_salva_alleggerita(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($user);

        $prodotto = Product::factory()->create([
            'product_category_id' => ProductCategory::factory()->create()->id,
        ]);

        $immagine = imagecreatetruecolor(6000, 4000);
        ob_start();
        imagejpeg($immagine, null, 98);
        $foto = UploadedFile::fake()->createWithContent('foto.jpg', ob_get_clean());

        Livewire::test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('data.images.prima', $foto)
            ->call('save')
            ->assertHasNoFormErrors();

        $media = $prodotto->fresh()->getFirstMedia('images');
        [$larghezza, $altezza] = getimagesize($media->getPath());
        $this->assertSame([2560, 1707], [$larghezza, $altezza]);
    }

    /**
     * Oltre il limite FilePond deve dirlo prima dell'invio, non il server
     * con un 500: il campo eredita il limite della media library.
     */
    #[Test]
    public function il_campo_conosce_il_limite_della_media_library(): void
    {
        $campo = SpatieMediaLibraryFileUpload::make('images');

        $this->assertSame(intdiv(config('media-library.max_file_size'), 1024), $campo->getMaxSize());
        $this->assertGreaterThanOrEqual(1024 * 1024 * 50, config('media-library.max_file_size'));
    }

    /**
     * Cio' che non si alleggerisce (gallery, PNG con profilo colore) arriva
     * intero alla media library: oltre i 10 MB di serie deve passare.
     */
    #[Test]
    public function la_media_library_accetta_un_file_da_dodici_mega(): void
    {
        Storage::fake('public');
        $prodotto = Product::factory()->create();

        $prodotto->addMediaFromString($this->fotoDa(12)->get())
            ->usingFileName('foto.jpg')
            ->toMediaCollection('images');

        $this->assertCount(1, $prodotto->fresh()->getMedia('images'));
    }

    /**
     * Il caso della segreteria (02/10/2026): un PNG da 700 KB con un profilo
     * colore difettoso mandava in 500 la conversione sul GD di Linux. Arriva
     * in archivio senza profilo, con le conversioni fatte.
     */
    #[Test]
    public function il_png_con_profilo_difettoso_si_salva_senza_profilo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($user);
        $prodotto = Product::factory()->create([
            'product_category_id' => ProductCategory::factory()->create()->id,
        ]);

        ob_start();
        imagepng(imagecreatetruecolor(1200, 1200));
        $png = ob_get_clean();
        $dati = "sRGB\0\0".gzcompress('profilo difettoso');
        $png = substr($png, 0, 33).pack('N', strlen($dati)).'iCCP'.$dati.pack('N', crc32('iCCP'.$dati)).substr($png, 33);

        Livewire::test(EditProduct::class, ['record' => $prodotto->getRouteKey()])
            ->set('data.images.prima', UploadedFile::fake()->createWithContent('maglia.png', $png))
            ->call('save')
            ->assertHasNoFormErrors();

        $media = $prodotto->fresh()->getFirstMedia('images');
        $this->assertStringNotContainsString('iCCP', file_get_contents($media->getPath()));
        $this->assertTrue($media->hasGeneratedConversion('zoom'));
    }

    /** Anche i FileUpload semplici (non media library) salvano la foto alleggerita. */
    #[Test]
    public function l_immagine_della_categoria_si_salva_alleggerita(): void
    {
        $disco = config('filament.default_filesystem_disk');
        Storage::fake($disco);
        $user = User::factory()->create();
        $user->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        $this->actingAs($user);
        $categoria = ProductCategory::factory()->create();

        $immagine = imagecreatetruecolor(6000, 4000);
        ob_start();
        imagejpeg($immagine, null, 98);
        $foto = UploadedFile::fake()->createWithContent('categoria.jpg', ob_get_clean());

        Livewire::test(EditProductCategory::class, ['record' => $categoria->getRouteKey()])
            ->set('data.image', null)
            ->set('data.image.'.Str::uuid()->toString(), $foto)
            ->call('save')
            ->assertHasNoFormErrors();

        $percorso = $categoria->fresh()->image;
        $this->assertNotEmpty($percorso);
        $this->assertSame(2560, getimagesize(Storage::disk($disco)->path($percorso))[0]);
    }

    /** Un JPEG valido gonfiato fino a `$mega` MB con dati in coda. */
    private function fotoDa(int $mega): UploadedFile
    {
        $immagine = imagecreatetruecolor(800, 800);
        ob_start();
        imagejpeg($immagine);
        $jpeg = ob_get_clean();

        return UploadedFile::fake()->createWithContent('foto.jpg', $jpeg.str_repeat("\0", $mega * 1024 * 1024));
    }
}
