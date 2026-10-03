<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mensagens de validação
    |--------------------------------------------------------------------------
    |
    | Mensagens padrão das regras de validação, em pt-BR. As frases começam
    | por "O campo :attribute" para não depender do gênero do nome do campo.
    | Os nomes legíveis dos campos ficam em "attributes", no fim do arquivo.
    |
    */

    'accepted' => 'O campo :attribute precisa ser aceito.',
    'accepted_if' => 'O campo :attribute precisa ser aceito quando :other for :value.',
    'active_url' => 'O campo :attribute precisa ser uma URL válida.',
    'after' => 'O campo :attribute precisa ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute precisa ser uma data igual ou posterior a :date.',
    'alpha' => 'O campo :attribute só pode conter letras.',
    'alpha_dash' => 'O campo :attribute só pode conter letras, números, hifens e sublinhados.',
    'alpha_num' => 'O campo :attribute só pode conter letras e números.',
    'any_of' => 'O campo :attribute é inválido.',
    'array' => 'O campo :attribute precisa ser uma lista.',
    'ascii' => 'O campo :attribute só pode conter letras sem acento, números e símbolos simples.',
    'before' => 'O campo :attribute precisa ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute precisa ser uma data igual ou anterior a :date.',
    'between' => [
        'array' => 'O campo :attribute precisa ter entre :min e :max itens.',
        'file' => 'O arquivo do campo :attribute precisa ter entre :min e :max KB.',
        'numeric' => 'O campo :attribute precisa estar entre :min e :max.',
        'string' => 'O campo :attribute precisa ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute precisa ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'contains' => 'O campo :attribute não contém um valor obrigatório.',
    'current_password' => 'A senha está incorreta.',
    'date' => 'O campo :attribute precisa ser uma data válida.',
    'date_equals' => 'O campo :attribute precisa ser uma data igual a :date.',
    'date_format' => 'O campo :attribute precisa seguir o formato :format.',
    'decimal' => 'O campo :attribute precisa ter :decimal casas decimais.',
    'declined' => 'O campo :attribute precisa ser recusado.',
    'declined_if' => 'O campo :attribute precisa ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other precisam ser diferentes.',
    'digits' => 'O campo :attribute precisa ter :digits dígitos.',
    'digits_between' => 'O campo :attribute precisa ter entre :min e :max dígitos.',
    'dimensions' => 'A imagem do campo :attribute tem dimensões inválidas.',
    'distinct' => 'O campo :attribute tem um valor repetido.',
    'doesnt_contain' => 'O campo :attribute não pode conter nenhum destes valores: :values.',
    'doesnt_end_with' => 'O campo :attribute não pode terminar com nenhum destes valores: :values.',
    'doesnt_start_with' => 'O campo :attribute não pode começar com nenhum destes valores: :values.',
    'email' => 'O campo :attribute precisa ser um endereço de e-mail válido.',
    'encoding' => 'O campo :attribute precisa estar codificado em :encoding.',
    'ends_with' => 'O campo :attribute precisa terminar com um destes valores: :values.',
    'enum' => 'O valor selecionado no campo :attribute é inválido.',
    'exists' => 'O valor selecionado no campo :attribute é inválido.',
    'extensions' => 'O campo :attribute precisa ter uma destas extensões: :values.',
    'file' => 'O campo :attribute precisa ser um arquivo.',
    'filled' => 'O campo :attribute precisa ser preenchido.',
    'gt' => [
        'array' => 'O campo :attribute precisa ter mais de :value itens.',
        'file' => 'O arquivo do campo :attribute precisa ter mais de :value KB.',
        'numeric' => 'O campo :attribute precisa ser maior que :value.',
        'string' => 'O campo :attribute precisa ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute precisa ter :value itens ou mais.',
        'file' => 'O arquivo do campo :attribute precisa ter :value KB ou mais.',
        'numeric' => 'O campo :attribute precisa ser maior ou igual a :value.',
        'string' => 'O campo :attribute precisa ter :value caracteres ou mais.',
    ],
    'hex_color' => 'O campo :attribute precisa ser uma cor hexadecimal válida.',
    'image' => 'O campo :attribute precisa ser uma imagem.',
    'in' => 'O valor selecionado no campo :attribute é inválido.',
    'in_array' => 'O campo :attribute precisa existir em :other.',
    'in_array_keys' => 'O campo :attribute precisa conter pelo menos uma destas chaves: :values.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'ip' => 'O campo :attribute precisa ser um endereço IP válido.',
    'ipv4' => 'O campo :attribute precisa ser um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute precisa ser um endereço IPv6 válido.',
    'json' => 'O campo :attribute precisa ser um texto JSON válido.',
    'list' => 'O campo :attribute precisa ser uma lista.',
    'lowercase' => 'O campo :attribute precisa estar em letras minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute precisa ter menos de :value itens.',
        'file' => 'O arquivo do campo :attribute precisa ter menos de :value KB.',
        'numeric' => 'O campo :attribute precisa ser menor que :value.',
        'string' => 'O campo :attribute precisa ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute não pode ter mais de :value itens.',
        'file' => 'O arquivo do campo :attribute precisa ter :value KB ou menos.',
        'numeric' => 'O campo :attribute precisa ser menor ou igual a :value.',
        'string' => 'O campo :attribute precisa ter :value caracteres ou menos.',
    ],
    'mac_address' => 'O campo :attribute precisa ser um endereço MAC válido.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O arquivo do campo :attribute não pode ter mais de :max KB.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute não pode ter mais de :max dígitos.',
    'mimes' => 'O campo :attribute precisa ser um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute precisa ser um arquivo do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute precisa ter pelo menos :min itens.',
        'file' => 'O arquivo do campo :attribute precisa ter pelo menos :min KB.',
        'numeric' => 'O campo :attribute precisa ser pelo menos :min.',
        'string' => 'O campo :attribute precisa ter pelo menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute precisa ter pelo menos :min dígitos.',
    'missing' => 'O campo :attribute não deve ser enviado.',
    'missing_if' => 'O campo :attribute não deve ser enviado quando :other for :value.',
    'missing_unless' => 'O campo :attribute não deve ser enviado, a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute não deve ser enviado quando :values for informado.',
    'missing_with_all' => 'O campo :attribute não deve ser enviado quando :values forem informados.',
    'multiple_of' => 'O campo :attribute precisa ser múltiplo de :value.',
    'not_in' => 'O valor selecionado no campo :attribute é inválido.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute precisa ser um número.',
    'password' => [
        'letters' => 'O campo :attribute precisa ter pelo menos uma letra.',
        'mixed' => 'O campo :attribute precisa ter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute precisa ter pelo menos um número.',
        'symbols' => 'O campo :attribute precisa ter pelo menos um símbolo.',
        'uncompromised' => 'O valor do campo :attribute apareceu em um vazamento de dados. Escolha outro.',
    ],
    'present' => 'O campo :attribute precisa ser enviado.',
    'present_if' => 'O campo :attribute precisa ser enviado quando :other for :value.',
    'present_unless' => 'O campo :attribute precisa ser enviado, a menos que :other seja :value.',
    'present_with' => 'O campo :attribute precisa ser enviado quando :values for informado.',
    'present_with_all' => 'O campo :attribute precisa ser enviado quando :values forem informados.',
    'prohibited' => 'O campo :attribute não é permitido.',
    'prohibited_if' => 'O campo :attribute não é permitido quando :other for :value.',
    'prohibited_if_accepted' => 'O campo :attribute não é permitido quando :other for aceito.',
    'prohibited_if_declined' => 'O campo :attribute não é permitido quando :other for recusado.',
    'prohibited_unless' => 'O campo :attribute não é permitido, a menos que :other esteja em :values.',
    'prohibits' => 'O campo :attribute impede que :other seja enviado.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute precisa conter itens para: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório, a menos que :other esteja em :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values for informado.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values forem informados.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não for informado.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum destes for informado: :values.',
    'same' => 'Os campos :attribute e :other precisam ser iguais.',
    'size' => [
        'array' => 'O campo :attribute precisa ter :size itens.',
        'file' => 'O arquivo do campo :attribute precisa ter :size KB.',
        'numeric' => 'O campo :attribute precisa ser :size.',
        'string' => 'O campo :attribute precisa ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute precisa começar com um destes valores: :values.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'timezone' => 'O campo :attribute precisa ser um fuso horário válido.',
    'unique' => 'O valor do campo :attribute já está em uso.',
    'uploaded' => 'Não foi possível enviar o arquivo do campo :attribute. Confira o tamanho e tente de novo.',
    'uppercase' => 'O campo :attribute precisa estar em letras maiúsculas.',
    'url' => 'O campo :attribute precisa ser uma URL válida.',
    'ulid' => 'O campo :attribute precisa ser um ULID válido.',
    'uuid' => 'O campo :attribute precisa ser um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Mensagens específicas por campo
    |--------------------------------------------------------------------------
    |
    | Use "campo.regra" para uma frase mais humana que a genérica. Mensagens
    | passadas direto no validate() ou em messages() de um FormRequest têm
    | prioridade sobre estas.
    |
    */

    'custom' => [
        'email' => [
            'unique' => 'Este e-mail já está cadastrado.',
        ],
        'license_plate' => [
            'unique' => 'Esta placa já está cadastrada.',
        ],
        'renavam' => [
            'unique' => 'Este RENAVAM já está cadastrado.',
        ],
        'chassis' => [
            'unique' => 'Este chassi já está cadastrado.',
        ],
        'invoices' => [
            'required_with' => 'Informe ao menos uma nota fiscal (PDF ou XML) ao vincular uma oficina cadastrada.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes legíveis dos campos
    |--------------------------------------------------------------------------
    |
    | Substituem :attribute, :other e :values nas mensagens. Seguem o
    | glossário do produto (placa, chassi, RENAVAM, número do CRV,
    | quilometragem, nota fiscal). Chaves com * valem para itens de listas.
    |
    */

    'attributes' => [
        // Conta e acesso
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'phone' => 'telefone',
        'whatsapp' => 'WhatsApp',
        'document' => 'CPF ou CNPJ',
        'user_type' => 'tipo de conta',
        'portal' => 'área de acesso',
        'terms_accepted' => 'termos de uso',
        'token' => 'token',
        'code' => 'código',
        'recovery_code' => 'código de recuperação',
        'avatar' => 'foto de perfil',
        'device_type' => 'tipo de dispositivo',
        'cf-turnstile-response' => 'verificação anti-robô',

        // Endereço
        'cep' => 'CEP',
        'postal_code' => 'CEP',
        'street' => 'rua',
        'number' => 'número',
        'complement' => 'complemento',
        'neighborhood' => 'bairro',
        'city' => 'cidade',
        'state' => 'estado',
        'country' => 'país',

        // Contato e perfil da oficina
        'subject' => 'assunto',
        'message' => 'mensagem',
        'website' => 'site',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'logo' => 'logotipo',

        // Veículo
        'vehicle_id' => 'veículo',
        'license_plate' => 'placa',
        'renavam' => 'RENAVAM',
        'crv_number' => 'número do CRV',
        'chassis' => 'chassi',
        'brand' => 'marca',
        'model' => 'modelo',
        'year' => 'ano',
        'color' => 'cor',
        'motorization' => 'motorização',
        'engine' => 'código do motor',
        'current_kilometers' => 'quilometragem atual',
        'purchase_date' => 'data de compra',
        'plate_changed_at' => 'data da troca de placa',
        'cover' => 'capa paisagem',
        'cover_portrait' => 'capa retrato',
        'crlv' => 'CRLV-e',
        'power_of_attorney' => 'procuração',
        'crlv_verification_token' => 'leitura do CRLV-e',

        // Manutenção e ordem de serviço
        'maintenance_id' => 'manutenção',
        'maintenance_type' => 'tipo de manutenção',
        'maintenance_date' => 'data da manutenção',
        'kilometers' => 'quilometragem',
        'service_category' => 'categoria',
        'description' => 'descrição',
        'workshop_id' => 'oficina',
        'workshop_name' => 'nome da oficina',
        'is_manufacturer_required' => 'revisão obrigatória',
        'return_to' => 'página de retorno',
        'invoices' => 'notas fiscais',
        'invoices.*' => 'nota fiscal',
        'file' => 'arquivo',
        'invoice_type' => 'tipo da nota fiscal',
        'invoice_number' => 'número da nota fiscal',
        'invoice_date' => 'data da nota fiscal',
        'total_amount' => 'valor total',
        'photo' => 'foto',
        'photos' => 'fotos',
        'photos.*' => 'foto',
        'photos.*.*' => 'foto',
        'delete_photos' => 'fotos para excluir',
        'stage' => 'etapa',
        'maintenance_item_id' => 'item da manutenção',
        'items' => 'itens',
        'items.*.name' => 'nome do item',
        'items.*.description' => 'descrição do item',
        'items.*.part_number' => 'código da peça',
        'items.*.quantity' => 'quantidade',
        'items.*.unit_price' => 'valor unitário',
        'items.*.total_price' => 'valor total do item',
        'items.*.warranty_template_id' => 'garantia do item',
        'general_warranty_template_id' => 'garantia geral',
        'checklists' => 'checklists',
        'checklists.*.checklist_type' => 'tipo de checklist',
        'checklists.*.items' => 'itens do checklist',
        'checklists.*.notes' => 'observações do checklist',

        // Modelos de garantia e de mensagem
        'body' => 'texto',
        'scope' => 'escopo',
        'duration_days' => 'duração (dias)',
        'is_active' => 'ativo',
        'title' => 'título',
        'trigger' => 'gatilho',
        'lead_kilometers' => 'antecedência (km)',
        'min_days_since_service' => 'dias desde o serviço',

        // Blog e catálogo (admin)
        'slug' => 'slug',
        'excerpt' => 'resumo',
        'content' => 'conteúdo',
        'meta_title' => 'meta título',
        'meta_description' => 'meta descrição',
        'blog_category_id' => 'categoria',
        'author_id' => 'autor',
        'published_at' => 'data de publicação',
        'status' => 'status',
        'cover_art' => 'ilustração de capa',
        'cover_photo_alt' => 'texto alternativo da capa',
        'publish_mode' => 'publicação',
        'keep_slug' => 'manter o endereço atual',
    ],

];
