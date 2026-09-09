<?php

namespace Webkul\TopwebChat\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RealEstateDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->seedRoles();
        $this->seedUsers();
        $this->seedPipeline();
        $this->seedSources();
        $this->seedTypes();
        $this->seedProducts();
        $this->seedPersons();
        $this->seedLeads();
    }

    private function seedRoles(): void
    {
        DB::table('roles')->delete();

        $roles = [
            [
                'id' => 1,
                'name' => 'Super Administrador',
                'description' => 'Acesso total ao sistema',
                'permission_type' => 'all',
                'permissions' => json_encode([]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'name' => 'Gerente de Vendas',
                'description' => 'Gerencia equipe de vendas e visualiza todos os leads',
                'permission_type' => 'custom',
                'permissions' => json_encode([
                    'dashboard',
                    'leads.index',
                    'leads.create',
                    'leads.edit',
                    'leads.view',
                    'leads.delete',
                    'leads.mass_delete',
                    'contacts.persons.index',
                    'contacts.persons.create',
                    'contacts.persons.edit',
                    'contacts.persons.view',
                    'contacts.organizations.index',
                    'contacts.organizations.create',
                    'contacts.organizations.edit',
                    'contacts.organizations.view',
                    'activities.index',
                    'activities.create',
                    'activities.edit',
                    'activities.view',
                    'topweb_chat.inbox',
                    'topweb_chat.inbox.view',
                    'topweb_chat.inbox.send',
                    'topweb_chat.inbox.create',
                    'topweb_chat.inbox.assign',
                    'topweb_chat.inbox.stage',
                    'topweb_chat.inbox.notes',
                    'topweb_chat.settings.index',
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 3,
                'name' => 'Corretor de Imóveis',
                'description' => 'Atende leads próprios e usa WhatsApp',
                'permission_type' => 'custom',
                'permissions' => json_encode([
                    'dashboard',
                    'leads.index',
                    'leads.create',
                    'leads.edit',
                    'leads.view',
                    'contacts.persons.index',
                    'contacts.persons.create',
                    'contacts.persons.edit',
                    'contacts.persons.view',
                    'activities.index',
                    'activities.create',
                    'activities.edit',
                    'activities.view',
                    'topweb_chat.inbox',
                    'topweb_chat.inbox.view',
                    'topweb_chat.inbox.send',
                    'topweb_chat.inbox.create',
                    'topweb_chat.inbox.assign',
                    'topweb_chat.inbox.stage',
                    'topweb_chat.inbox.notes',
                ]),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('roles')->insert($roles);
    }

    private function seedUsers(): void
    {
        DB::table('users')->delete();

        $users = [
            [
                'id' => 1,
                'name' => 'Administrador Topweb',
                'email' => 'admin@scgroup.com.br',
                'password' => Hash::make('admin123'),
                'role_id' => 1,
                'status' => 1,
                'view_permission' => 'global',
                'can_view_sensitive_data' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'name' => 'Carlos Mendes',
                'email' => 'carlos.mendes@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 2,
                'status' => 1,
                'view_permission' => 'global',
                'can_view_sensitive_data' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 3,
                'name' => 'Ana Paula Silva',
                'email' => 'ana.silva@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 3,
                'status' => 1,
                'view_permission' => 'individual',
                'can_view_sensitive_data' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 4,
                'name' => 'Roberto Almeida',
                'email' => 'roberto.almeida@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 3,
                'status' => 1,
                'view_permission' => 'individual',
                'can_view_sensitive_data' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 5,
                'name' => 'Fernanda Costa',
                'email' => 'fernanda.costa@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 3,
                'status' => 1,
                'view_permission' => 'individual',
                'can_view_sensitive_data' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 6,
                'name' => 'Marcelo Santos',
                'email' => 'marcelo.santos@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 3,
                'status' => 1,
                'view_permission' => 'individual',
                'can_view_sensitive_data' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 7,
                'name' => 'Juliana Oliveira',
                'email' => 'juliana.oliveira@topweb.com.br',
                'password' => Hash::make('corretor123'),
                'role_id' => 3,
                'status' => 1,
                'view_permission' => 'individual',
                'can_view_sensitive_data' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('users')->insert($users);
    }

    private function seedPipeline(): void
    {
        DB::table('lead_pipeline_stages')->delete();
        DB::table('lead_pipelines')->delete();

        $now = Carbon::now();

        DB::table('lead_pipelines')->insert([
            'id' => 1,
            'name' => 'Pipeline Imobiliário - Venda',
            'is_default' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('lead_pipelines')->insert([
            'id' => 2,
            'name' => 'Pipeline Imobiliário - Locação',
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stagesVenda = [
            [
                'id' => 1,
                'code' => 'novo',
                'name' => 'Novo Lead',
                'probability' => 10,
                'sort_order' => 1,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'contato_inicial',
                'name' => 'Contato Inicial',
                'probability' => 20,
                'sort_order' => 2,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'code' => 'qualificacao',
                'name' => 'Qualificação',
                'probability' => 30,
                'sort_order' => 3,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'code' => 'visita_agendada',
                'name' => 'Visita Agendada',
                'probability' => 50,
                'sort_order' => 4,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'code' => 'proposta',
                'name' => 'Proposta Enviada',
                'probability' => 70,
                'sort_order' => 5,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'code' => 'negociacao',
                'name' => 'Negociação',
                'probability' => 85,
                'sort_order' => 6,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'code' => 'fechado',
                'name' => 'Fechado - Ganho',
                'probability' => 100,
                'sort_order' => 7,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'code' => 'perdido',
                'name' => 'Fechado - Perdido',
                'probability' => 0,
                'sort_order' => 8,
                'lead_pipeline_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('lead_pipeline_stages')->insert($stagesVenda);

        $stagesLocacao = [
            [
                'id' => 9,
                'code' => 'novo',
                'name' => 'Novo Lead',
                'probability' => 10,
                'sort_order' => 1,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'code' => 'contato_inicial',
                'name' => 'Contato Inicial',
                'probability' => 20,
                'sort_order' => 2,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'code' => 'visita_agendada',
                'name' => 'Visita Agendada',
                'probability' => 50,
                'sort_order' => 3,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 12,
                'code' => 'proposta',
                'name' => 'Proposta de Locação',
                'probability' => 70,
                'sort_order' => 4,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 13,
                'code' => 'fechado',
                'name' => 'Contrato Assinado',
                'probability' => 100,
                'sort_order' => 5,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 14,
                'code' => 'perdido',
                'name' => 'Não Fechado',
                'probability' => 0,
                'sort_order' => 6,
                'lead_pipeline_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('lead_pipeline_stages')->insert($stagesLocacao);
    }

    private function seedSources(): void
    {
        DB::table('lead_sources')->delete();

        $now = Carbon::now();

        DB::table('lead_sources')->insert([
            ['id' => 1, 'name' => 'Portal Imobiliário (Zap/OLX)', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Indicação de Cliente', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'WhatsApp Entrada', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Redes Sociais (Instagram/Facebook)', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Placa no Imóvel', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Site Próprio', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Parceria com Construtora', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Evento/Feira Imobiliária', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function seedTypes(): void
    {
        DB::table('lead_types')->delete();

        $now = Carbon::now();

        DB::table('lead_types')->insert([
            ['id' => 1, 'name' => 'Venda', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Locação', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Permuta', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function seedProducts(): void
    {
        DB::table('products')->delete();

        $now = Carbon::now();

        $products = [
            [
                'id' => 1,
                'name' => 'Apartamento 2 Quartos - Centro',
                'sku' => 'APT-CENTRO-2Q',
                'description' => 'Apartamento de 2 quartos, 1 suíte, 1 vaga de garagem, 65m², próximo ao metrô e comércio.',
                'price' => 450000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Apartamento 3 Quartos - Jardins',
                'sku' => 'APT-JARDINS-3Q',
                'description' => 'Apartamento de 3 quartos, 2 suítes, 2 vagas, 120m², varanda gourmet, lazer completo.',
                'price' => 1200000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Casa em Condomínio - Alphaville',
                'sku' => 'CAS-ALPHA-4Q',
                'description' => 'Casa de 4 suítes, 4 vagas, 350m² terreno, 280m² construído, piscina, churrasqueira.',
                'price' => 3500000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Cobertura Duplex - Vila Madalena',
                'sku' => 'COB-VM-3Q',
                'description' => 'Cobertura duplex 3 quartos, 2 vagas, 180m², terraço com churrasqueira, vista panorâmica.',
                'price' => 2100000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => 'Studio Mobiliado - Pinheiros',
                'sku' => 'STU-PIN-1Q',
                'description' => 'Studio mobiliado 35m², 1 vaga, prédio com lazer, ideal para investimento/airbnb.',
                'price' => 380000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'name' => 'Terreno - Condomínio Fechado - Cotia',
                'sku' => 'TER-COT-500',
                'description' => 'Terreno 500m² em condomínio de alto padrão, topografia plana, infraestrutura completa.',
                'price' => 450000.00,
                'type' => 'real_estate',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'name' => 'Apartamento para Locação - Vila Olímpia',
                'sku' => 'LOC-VO-2Q',
                'description' => 'Apartamento 2 quartos, 1 vaga, 70m², mobiliado, prédio com lazer, próximo ao shopping.',
                'price' => 6500.00,
                'type' => 'real_estate_rental',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'name' => 'Casa para Locação - Granja Viana',
                'sku' => 'LOC-GV-3Q',
                'description' => 'Casa 3 quartos, 2 vagas, 200m², quintal, piscina, condomínio fechado.',
                'price' => 8500.00,
                'type' => 'real_estate_rental',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('products')->insert($products);
    }

    private function seedPersons(): void
    {
        DB::table('persons')->delete();

        $now = Carbon::now();

        $persons = [
            [
                'id' => 1,
                'name' => 'João Carlos Ferreira',
                'first_name' => 'João Carlos',
                'last_name' => 'Ferreira',
                'email' => 'joao.ferreira@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11999887766'],
                    ['type' => 'phone', 'value' => '1133224455'],
                ]),
                'address' => json_encode([
                    'street' => 'Rua Augusta',
                    'number' => '1500',
                    'neighborhood' => 'Consolação',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '01305-000',
                    'country' => 'BR',
                ]),
                'user_id' => 3,
                'created_at' => $now->subDays(30),
                'updated_at' => $now->subDays(30),
            ],
            [
                'id' => 2,
                'name' => 'Maria Helena Santos',
                'first_name' => 'Maria Helena',
                'last_name' => 'Santos',
                'email' => 'maria.santos@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11988776655'],
                ]),
                'address' => json_encode([
                    'street' => 'Av. Paulista',
                    'number' => '2000',
                    'neighborhood' => 'Bela Vista',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '01310-200',
                    'country' => 'BR',
                ]),
                'user_id' => 4,
                'created_at' => $now->subDays(25),
                'updated_at' => $now->subDays(25),
            ],
            [
                'id' => 3,
                'name' => 'Pedro Henrique Lima',
                'first_name' => 'Pedro Henrique',
                'last_name' => 'Lima',
                'email' => 'pedro.lima@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11977665544'],
                    ['type' => 'phone', 'value' => '1144556677'],
                ]),
                'address' => json_encode([
                    'street' => 'Rua Oscar Freire',
                    'number' => '800',
                    'neighborhood' => 'Jardins',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '01426-001',
                    'country' => 'BR',
                ]),
                'user_id' => 5,
                'created_at' => $now->subDays(20),
                'updated_at' => $now->subDays(20),
            ],
            [
                'id' => 4,
                'name' => 'Carla Regina Almeida',
                'first_name' => 'Carla Regina',
                'last_name' => 'Almeida',
                'email' => 'carla.almeida@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11966554433'],
                ]),
                'address' => json_encode([
                    'street' => 'Rua Haddock Lobo',
                    'number' => '1200',
                    'neighborhood' => 'Cerqueira César',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '01414-001',
                    'country' => 'BR',
                ]),
                'user_id' => 6,
                'created_at' => $now->subDays(15),
                'updated_at' => $now->subDays(15),
            ],
            [
                'id' => 5,
                'name' => 'Ricardo Augusto Souza',
                'first_name' => 'Ricardo Augusto',
                'last_name' => 'Souza',
                'email' => 'ricardo.souza@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11955443322'],
                    ['type' => 'phone', 'value' => '1155667788'],
                ]),
                'address' => json_encode([
                    'street' => 'Av. Faria Lima',
                    'number' => '3000',
                    'neighborhood' => 'Itaim Bibi',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '04538-132',
                    'country' => 'BR',
                ]),
                'user_id' => 7,
                'created_at' => $now->subDays(10),
                'updated_at' => $now->subDays(10),
            ],
            [
                'id' => 6,
                'name' => 'Luciana Beatriz Costa',
                'first_name' => 'Luciana Beatriz',
                'last_name' => 'Costa',
                'email' => 'luciana.costa@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11944332211'],
                ]),
                'address' => json_encode([
                    'street' => 'Rua Mateus Grou',
                    'number' => '450',
                    'neighborhood' => 'Pinheiros',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '05417-020',
                    'country' => 'BR',
                ]),
                'user_id' => 3,
                'created_at' => $now->subDays(5),
                'updated_at' => $now->subDays(5),
            ],
            [
                'id' => 7,
                'name' => 'André Luiz Pereira',
                'first_name' => 'André Luiz',
                'last_name' => 'Pereira',
                'email' => 'andre.pereira@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11933221100'],
                    ['type' => 'phone', 'value' => '1166778899'],
                ]),
                'address' => json_encode([
                    'street' => 'Rua Joaquim Távora',
                    'number' => '600',
                    'neighborhood' => 'Vila Mariana',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '04015-012',
                    'country' => 'BR',
                ]),
                'user_id' => 4,
                'created_at' => $now->subDays(3),
                'updated_at' => $now->subDays(3),
            ],
            [
                'id' => 8,
                'name' => 'Patrícia Gomes Ribeiro',
                'first_name' => 'Patrícia Gomes',
                'last_name' => 'Ribeiro',
                'email' => 'patricia.ribeiro@email.com',
                'contact_numbers' => json_encode([
                    ['type' => 'mobile', 'value' => '11922110099'],
                ]),
                'address' => json_encode([
                    'street' => 'Av. Brigadeiro Faria Lima',
                    'number' => '4000',
                    'neighborhood' => 'Itaim Bibi',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'postcode' => '04538-133',
                    'country' => 'BR',
                ]),
                'user_id' => 5,
                'created_at' => $now->subDay(),
                'updated_at' => $now->subDay(),
            ],
        ];

        DB::table('persons')->insert($persons);
    }

    private function seedLeads(): void
    {
        DB::table('leads')->delete();

        $now = Carbon::now();

        $leads = [
            [
                'id' => 1,
                'title' => 'Apartamento 2 Quartos - Centro - João Ferreira',
                'person_id' => 1,
                'user_id' => 3,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 3,
                'lead_source_id' => 3,
                'lead_type_id' => 1,
                'lead_value' => 450000.00,
                'description' => 'Cliente busca apartamento 2 quartos no centro, até R$ 500k. Prefere próximo ao metrô. Já tem FGTS para entrada.',
                'created_at' => $now->subDays(28),
                'updated_at' => $now->subDays(2),
            ],
            [
                'id' => 2,
                'title' => 'Casa em Condomínio - Alphaville - Maria Santos',
                'person_id' => 2,
                'user_id' => 4,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 5,
                'lead_source_id' => 2,
                'lead_type_id' => 1,
                'lead_value' => 3500000.00,
                'description' => 'Cliente indicada por corretor parceiro. Busca casa 4 suítes em Alphaville. Orçamento até R$ 4M. Agendou visita para sábado.',
                'created_at' => $now->subDays(23),
                'updated_at' => $now->subDays(1),
            ],
            [
                'id' => 3,
                'title' => 'Cobertura Duplex - Vila Madalena - Pedro Lima',
                'person_id' => 3,
                'user_id' => 5,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 4,
                'lead_source_id' => 1,
                'lead_type_id' => 1,
                'lead_value' => 2100000.00,
                'description' => 'Lead do Zap Imóveis. Busca cobertura na Vila Madalena/Pinheiros. Tem apartamento para dar de entrada (valor ~R$ 800k).',
                'created_at' => $now->subDays(18),
                'updated_at' => $now->subDays(3),
            ],
            [
                'id' => 4,
                'title' => 'Studio para Investimento - Pinheiros - Carla Almeida',
                'person_id' => 4,
                'user_id' => 6,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 2,
                'lead_source_id' => 4,
                'lead_type_id' => 1,
                'lead_value' => 380000.00,
                'description' => 'Investidora busca studio em Pinheiros para aluguel via Airbnb. Orçamento até R$ 400k. Quer rentabilidade acima de 0,8% ao mês.',
                'created_at' => $now->subDays(13),
                'updated_at' => $now->subDays(2),
            ],
            [
                'id' => 5,
                'title' => 'Terreno Condomínio Fechado - Cotia - Ricardo Souza',
                'person_id' => 5,
                'user_id' => 7,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 1,
                'lead_source_id' => 5,
                'lead_type_id' => 1,
                'lead_value' => 450000.00,
                'description' => 'Cliente viu placa no condomínio. Busca terreno 500m²+ para construir casa de praia. Prefere topografia plana.',
                'created_at' => $now->subDays(8),
                'updated_at' => $now->subDays(8),
            ],
            [
                'id' => 6,
                'title' => 'Apartamento Locação - Vila Olímpia - Luciana Costa',
                'person_id' => 6,
                'user_id' => 3,
                'lead_pipeline_id' => 2,
                'lead_pipeline_stage_id' => 10,
                'lead_source_id' => 3,
                'lead_type_id' => 2,
                'lead_value' => 6500.00,
                'description' => 'Lead via WhatsApp. Busca apto 2 quartos mobiliado na Vila Olímpia para locação. Mudança urgente - 30 dias. Pet friendly.',
                'created_at' => $now->subDays(4),
                'updated_at' => $now->subDays(1),
            ],
            [
                'id' => 7,
                'title' => 'Casa Locação - Granja Viana - André Pereira',
                'person_id' => 7,
                'user_id' => 4,
                'lead_pipeline_id' => 2,
                'lead_pipeline_stage_id' => 11,
                'lead_source_id' => 6,
                'lead_type_id' => 2,
                'lead_value' => 8500.00,
                'description' => 'Lead do site. Família com 2 filhos e 1 cachorro. Busca casa 3 quartos em Granja Viana. Orçamento até R$ 9k. Disponível para visita fim de semana.',
                'created_at' => $now->subDays(2),
                'updated_at' => $now->subDay(),
            ],
            [
                'id' => 8,
                'title' => 'Apartamento 3 Quartos - Jardins - Patrícia Ribeiro',
                'person_id' => 8,
                'user_id' => 5,
                'lead_pipeline_id' => 1,
                'lead_pipeline_stage_id' => 6,
                'lead_source_id' => 7,
                'lead_type_id' => 1,
                'lead_value' => 1200000.00,
                'description' => 'Cliente de construtora parceira. Busca apto 3 quartos nos Jardins. Tem imóvel para permuta (valor ~R$ 700k). Negociação avançada.',
                'created_at' => $now->subDay(),
                'updated_at' => $now,
            ],
        ];

        DB::table('leads')->insert($leads);
    }
}
