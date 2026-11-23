<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminMenuSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    public function test_all_admin_menu_destinations_render_for_authorized_owner(): void
    {
        $this->withoutVite();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $visited = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName() ?? '';
            if (! str_starts_with($name, 'filament.admin.') || ! in_array('GET', $route->methods(), true) || $route->parameterNames()
                || (! str_contains($name, '.pages.') && ! str_ends_with($name, '.index'))) {
                continue;
            }
            $response = $this->get(route($name));
            $this->assertSame(200, $response->getStatusCode(), $name.' failed to render');
            $visited[] = $name;
        }
        $this->assertGreaterThanOrEqual(15, count($visited));
    }
}
