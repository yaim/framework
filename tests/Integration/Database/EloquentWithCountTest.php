<?php

namespace Illuminate\Tests\Integration\Database\EloquentWithCountTest;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Tests\Integration\Database\DatabaseTestCase;

/**
 * @group integration
 */
class EloquentWithCountTest extends DatabaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.debug', 'true');

        $app['config']->set('database.default', 'conn1');

        $app['config']->set('database.connections.conn1', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('database.connections.conn2', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('one', function (Blueprint $table) {
            $table->increments('id');
        });

        Schema::create('two', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('one_id');
        });

        Schema::create('three', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('two_id');
        });

        Schema::create('four', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('one_id');
        });

        Schema::connection('conn2')->create('five', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('one_id');
        });
    }

    public function testItBasic()
    {
        $one = Model1::create();
        $two = $one->twos()->Create();
        $two->threes()->Create();

        $results = Model1::withCount([
            'twos' => function ($query) {
                $query->where('id', '>=', 1);
            },
        ]);

        $this->assertEquals([
            ['id' => 1, 'twos_count' => 1],
        ], $results->get()->toArray());
    }

    public function testCrossConnection()
    {
        $one  = Model1::create();
        $five = $one->fives()->Create();

        $this->assertEquals([
            ['id' => 1, 'fives_count' => 1],
        ], Model1::withCount('fives')->get()->toArray());
    }

    public function testGlobalScopes()
    {
        $one = Model1::create();
        $one->fours()->create();

        $result = Model1::withCount('fours')->first();
        $this->assertEquals(0, $result->fours_count);

        $result = Model1::withCount('allFours')->first();
        $this->assertEquals(1, $result->all_fours_count);
    }
}

class Model1 extends Model
{
    public $table = 'one';
    public $timestamps = false;
    protected $guarded = ['id'];

    public function twos()
    {
        return $this->hasMany(Model2::class, 'one_id');
    }

    public function fours()
    {
        return $this->hasMany(Model4::class, 'one_id');
    }

    public function fives()
    {
        return $this->hasMany(Model5::class, 'one_id');
    }

    public function allFours()
    {
        return $this->fours()->withoutGlobalScopes();
    }
}

class Model2 extends Model
{
    public $table = 'two';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $withCount = ['threes'];

    public function threes()
    {
        return $this->hasMany(Model3::class, 'two_id');
    }
}

class Model3 extends Model
{
    public $table = 'three';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('app', function ($builder) {
            $builder->where('idz', '>', 0);
        });
    }
}

class Model4 extends Model
{
    public $table = 'four';
    public $timestamps = false;
    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('app', function ($builder) {
            $builder->where('id', '>', 1);
        });
    }
}

class Model5 extends Model
{
    public $table = 'five';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $connection = 'conn2';
}
