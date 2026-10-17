<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teams = config('permission.teams');

        $tableNames = config('permission.table_names');

        $columnNames = config('permission.column_names');

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';

        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        /*
        |--------------------------------------------------------------------------
        | Validate Spatie Permission Configuration
        |--------------------------------------------------------------------------
        */

        throw_if(
            empty($tableNames),
            Exception::class,
            'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.'
        );

        throw_if(
            $teams && empty($columnNames['team_foreign_key'] ?? null),
            Exception::class,
            'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.'
        );

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        |
        | Stores all permissions available in the PrimeIT system.
        |
        */

        Schema::create(
            $tableNames['permissions'],
            static function (Blueprint $table): void {
                $table->bigIncrements('id');

                $table->string('name');

                $table->string('guard_name');

                $table->timestamps();

                $table->unique(
                    ['name', 'guard_name']
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        |
        | Stores system roles.
        |
        | PrimeIT custom fields:
        |
        | - description
        | - is_system
        |
        */

        Schema::create(
            $tableNames['roles'],
            static function (Blueprint $table) use ($teams, $columnNames): void {
                $table->bigIncrements('id');

                /*
                |--------------------------------------------------------------------------
                | Teams Support
                |--------------------------------------------------------------------------
                */

                if ($teams || config('permission.testing')) {
                    $table->unsignedBigInteger(
                        $columnNames['team_foreign_key']
                    )->nullable();

                    $table->index(
                        $columnNames['team_foreign_key'],
                        'roles_team_foreign_key_index'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Role Information
                |--------------------------------------------------------------------------
                */

                $table->string('name');

                $table->string('guard_name');

                /*
                |--------------------------------------------------------------------------
                | PrimeIT Role Metadata
                |--------------------------------------------------------------------------
                */

                $table->text('description')
                    ->nullable();

                $table->boolean('is_system')
                    ->default(false);

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Role Uniqueness
                |--------------------------------------------------------------------------
                */

                if ($teams || config('permission.testing')) {
                    $table->unique(
                        [
                            $columnNames['team_foreign_key'],
                            'name',
                            'guard_name',
                        ]
                    );
                } else {
                    $table->unique(
                        [
                            'name',
                            'guard_name',
                        ]
                    );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Model Has Permissions
        |--------------------------------------------------------------------------
        |
        | Connects permissions to users/models.
        |
        */

        Schema::create(
            $tableNames['model_has_permissions'],
            static function (Blueprint $table) use (
                $tableNames,
                $columnNames,
                $pivotPermission,
                $teams
            ): void {
                $table->unsignedBigInteger(
                    $pivotPermission
                );

                $table->string('model_type');

                $table->unsignedBigInteger(
                    $columnNames['model_morph_key']
                );

                /*
                |--------------------------------------------------------------------------
                | Model Lookup Index
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        $columnNames['model_morph_key'],
                        'model_type',
                    ],
                    'model_has_permissions_model_id_model_type_index'
                );

                /*
                |--------------------------------------------------------------------------
                | Permission Foreign Key
                |--------------------------------------------------------------------------
                */

                $table->foreign(
                    $pivotPermission
                )
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                /*
                |--------------------------------------------------------------------------
                | Teams
                |--------------------------------------------------------------------------
                */

                if ($teams) {
                    $table->unsignedBigInteger(
                        $columnNames['team_foreign_key']
                    );

                    $table->index(
                        $columnNames['team_foreign_key'],
                        'model_has_permissions_team_foreign_key_index'
                    );

                    $table->primary(
                        [
                            $columnNames['team_foreign_key'],
                            $pivotPermission,
                            $columnNames['model_morph_key'],
                            'model_type',
                        ],
                        'model_has_permissions_permission_model_type_primary'
                    );
                } else {
                    $table->primary(
                        [
                            $pivotPermission,
                            $columnNames['model_morph_key'],
                            'model_type',
                        ],
                        'model_has_permissions_permission_model_type_primary'
                    );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Model Has Roles
        |--------------------------------------------------------------------------
        |
        | Connects roles to users/models.
        |
        */

        Schema::create(
            $tableNames['model_has_roles'],
            static function (Blueprint $table) use (
                $tableNames,
                $columnNames,
                $pivotRole,
                $teams
            ): void {
                $table->unsignedBigInteger(
                    $pivotRole
                );

                $table->string('model_type');

                $table->unsignedBigInteger(
                    $columnNames['model_morph_key']
                );

                /*
                |--------------------------------------------------------------------------
                | Model Lookup Index
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        $columnNames['model_morph_key'],
                        'model_type',
                    ],
                    'model_has_roles_model_id_model_type_index'
                );

                /*
                |--------------------------------------------------------------------------
                | Role Foreign Key
                |--------------------------------------------------------------------------
                */

                $table->foreign(
                    $pivotRole
                )
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                /*
                |--------------------------------------------------------------------------
                | Teams
                |--------------------------------------------------------------------------
                */

                if ($teams) {
                    $table->unsignedBigInteger(
                        $columnNames['team_foreign_key']
                    );

                    $table->index(
                        $columnNames['team_foreign_key'],
                        'model_has_roles_team_foreign_key_index'
                    );

                    $table->primary(
                        [
                            $columnNames['team_foreign_key'],
                            $pivotRole,
                            $columnNames['model_morph_key'],
                            'model_type',
                        ],
                        'model_has_roles_role_model_type_primary'
                    );
                } else {
                    $table->primary(
                        [
                            $pivotRole,
                            $columnNames['model_morph_key'],
                            'model_type',
                        ],
                        'model_has_roles_role_model_type_primary'
                    );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Role Has Permissions
        |--------------------------------------------------------------------------
        |
        | Connects roles to permissions.
        |
        */

        Schema::create(
            $tableNames['role_has_permissions'],
            static function (Blueprint $table) use (
                $tableNames,
                $pivotRole,
                $pivotPermission
            ): void {
                $table->unsignedBigInteger(
                    $pivotPermission
                );

                $table->unsignedBigInteger(
                    $pivotRole
                );

                /*
                |--------------------------------------------------------------------------
                | Permission Foreign Key
                |--------------------------------------------------------------------------
                */

                $table->foreign(
                    $pivotPermission
                )
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                /*
                |--------------------------------------------------------------------------
                | Role Foreign Key
                |--------------------------------------------------------------------------
                */

                $table->foreign(
                    $pivotRole
                )
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                /*
                |--------------------------------------------------------------------------
                | Composite Primary Key
                |--------------------------------------------------------------------------
                */

                $table->primary(
                    [
                        $pivotPermission,
                        $pivotRole,
                    ],
                    'role_has_permissions_permission_id_role_id_primary'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Clear Permission Cache
        |--------------------------------------------------------------------------
        */

        app('cache')
            ->store(
                config('permission.cache.store') !== 'default'
                    ? config('permission.cache.store')
                    : null
            )
            ->forget(
                config('permission.cache.key')
            );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(
            empty($tableNames),
            Exception::class,
            'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.'
        );

        /*
        |--------------------------------------------------------------------------
        | Drop Pivot Tables First
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists(
            $tableNames['role_has_permissions']
        );

        Schema::dropIfExists(
            $tableNames['model_has_roles']
        );

        Schema::dropIfExists(
            $tableNames['model_has_permissions']
        );

        /*
        |--------------------------------------------------------------------------
        | Drop Main Permission Tables
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists(
            $tableNames['roles']
        );

        Schema::dropIfExists(
            $tableNames['permissions']
        );
    }
};