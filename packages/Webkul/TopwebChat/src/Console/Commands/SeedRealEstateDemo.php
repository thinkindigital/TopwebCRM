<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;

class SeedRealEstateDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'topwebchat:seed-real-estate-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed real estate demo data (users, leads, products, pipeline)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Seeding Real Estate Demo data...');

        $this->call('db:seed', [
            '--class' => \Webkul\TopwebChat\Database\Seeders\RealEstateDemoSeeder::class,
            '--force' => true,
        ]);

        $this->info('Real Estate Demo data seeded successfully!');
        $this->newLine();
        $this->table(
            ['Usuário', 'Email', 'Senha', 'Perfil'],
            [
                ['Admin Topweb', 'admin@scgroup.com.br', 'admin123', 'Super Administrador'],
                ['Carlos Mendes', 'carlos.mendes@topweb.com.br', 'corretor123', 'Gerente de Vendas'],
                ['Ana Paula Silva', 'ana.silva@topweb.com.br', 'corretor123', 'Corretor de Imóveis'],
                ['Roberto Almeida', 'roberto.almeida@topweb.com.br', 'corretor123', 'Corretor de Imóveis'],
                ['Fernanda Costa', 'fernanda.costa@topweb.com.br', 'corretor123', 'Corretor de Imóveis'],
                ['Marcelo Santos', 'marcelo.santos@topweb.com.br', 'corretor123', 'Corretor de Imóveis'],
                ['Juliana Oliveira', 'juliana.oliveira@topweb.com.br', 'corretor123', 'Corretor de Imóveis'],
            ]
        );

        return self::SUCCESS;
    }
}