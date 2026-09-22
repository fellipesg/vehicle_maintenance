<?php

return [

    'company' => [
        'brand' => 'RevisaLog',
        // Preencher quando a empresa estiver constituída — aparece no rodapé e como controlador na política.
        'legal_name' => env('LEGAL_COMPANY_NAME'),
        'cnpj' => env('LEGAL_COMPANY_CNPJ'),
    ],

    'contact' => [
        'general' => env('LEGAL_CONTACT_EMAIL', 'contato@revisalog.com.br'),
        'privacy' => env('LEGAL_PRIVACY_EMAIL', 'privacidade@revisalog.com.br'),
    ],

    'terms_version' => '2026-08-21',

    'terms_of_use' => <<<'TEXT'
Termos de Uso — RevisaLog

1. Responsabilidade pelas informações
Ao cadastrar veículos, quilometragens, datas de manutenção, notas fiscais e demais dados, você declara que as informações são verdadeiras e de sua responsabilidade como proprietário ou representante autorizado.

2. Quilometragem e datas
A quilometragem informada deve refletir o hodômetro do veículo no momento do registro. Manutenções devem ser registradas com a quilometragem e a data corretas. O sistema utiliza esses dados para compor o histórico do veículo.

3. Isenção de responsabilidade
A plataforma RevisaLog atua como repositório do histórico informado pelos usuários. Não verificamos, em regra, a veracidade dos dados, não garantimos a completude do histórico e não nos responsabilizamos por informações falsas, incompletas ou desatualizadas fornecidas por terceiros.

4. Uso do histórico
O histórico exportado ou consultado reflete exclusivamente o que foi registrado na plataforma. Decisões de compra, venda, revisão ou garantia devem considerar fontes adicionais e inspeção presencial quando aplicável.

5. Notas fiscais e documentos
Documentos enviados (NF-e, DANFE, CRLV) são de responsabilidade de quem os anexa. O parse automático pode conter imprecisões; confira sempre os dados antes de salvar.

6. Aceite
Ao marcar que leu e aceita estes termos, você confirma ciência das condições acima e concorda em mantê-las atualizadas conforme alterações futuras publicadas nesta versão.
TEXT,

    'privacy_version' => '2026-09-21',

    'privacy_policy' => <<<'TEXT'
Política de Privacidade — RevisaLog

Esta política explica quais dados pessoais o RevisaLog trata, para quê e quais são os seus direitos, nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).

1. Quem é o controlador
O controlador dos dados é o RevisaLog, identificado no rodapé do site. Dúvidas e solicitações sobre privacidade devem ser enviadas ao encarregado pelo e-mail privacidade@revisalog.com.br.

2. Dados que coletamos
- Cadastro: nome, e-mail, telefone, CPF ou CNPJ, senha (armazenada com hash) e, quando você entra com Google, Facebook ou X, o identificador fornecido por esse provedor.
- Endereço: CEP, logradouro, cidade e estado, e as coordenadas geográficas derivadas do endereço (usadas para localizar oficinas).
- Veículo: placa, chassi, RENAVAM, marca, modelo, ano, quilometragem e documentos como o CRLV.
- Manutenções: datas, quilometragem, serviços, peças, valores, notas fiscais (NF-e/DANFE), fotos e garantias.
- Uso do app: token de notificação push do dispositivo e registros técnicos de acesso (IP, data e hora), mantidos por 6 meses conforme o Marco Civil da Internet.
- Contato: nome, e-mail e mensagem enviados pelo formulário Fale conosco.

3. Para que usamos os dados
- Prestar o serviço: manter o histórico de manutenções vinculado ao veículo, gerar o PDF do histórico e permitir a consulta por placa, chassi ou RENAVAM (execução de contrato).
- Segurança: autenticação, verificação em duas etapas, prevenção de fraude e de abuso (legítimo interesse).
- Comunicação: lembretes de manutenção, avisos de garantia e respostas a solicitações (execução de contrato e legítimo interesse).
- Obrigações legais e regulatórias, incluindo a guarda de registros de acesso (cumprimento de obrigação legal).

4. O histórico fica com o veículo
O histórico de manutenções é vinculado ao veículo, não ao proprietário. Quando o veículo muda de dono, o novo proprietário passa a ver os registros de manutenção anteriores, mas não os seus dados pessoais de cadastro (nome, e-mail, telefone, documento ou endereço).

5. Com quem compartilhamos
Não vendemos dados pessoais. Compartilhamos apenas o necessário com:
- Oficinas e lojistas que você autorizar a registrar ou consultar manutenções do seu veículo;
- Prestadores de infraestrutura que operam em nosso nome: Cloudflare (hospedagem, rede e armazenamento de arquivos), Twilio SendGrid (envio de e-mails), Google Firebase (notificações push) e OpenStreetMap/Nominatim (geolocalização de endereços);
- Autoridades públicas, quando exigido por lei ou ordem judicial.
Alguns desses prestadores armazenam dados fora do Brasil, com as salvaguardas previstas no art. 33 da LGPD.

6. Por quanto tempo guardamos
Mantemos os dados da conta enquanto ela estiver ativa. Ao excluir a conta, apagamos ou anonimizamos os dados pessoais, exceto os que precisamos manter por obrigação legal. Os registros de manutenção continuam vinculados ao veículo, sem identificar você.

7. Seus direitos
Você pode, a qualquer momento, solicitar: confirmação e acesso aos dados, correção, anonimização, bloqueio ou eliminação, portabilidade, informação sobre compartilhamento, e revogação do consentimento. Envie o pedido para privacidade@revisalog.com.br. Respondemos em até 15 dias. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).

8. Segurança
Usamos conexão criptografada (HTTPS), senhas com hash, verificação em duas etapas e controle de acesso por perfil. Nenhum sistema é totalmente imune a incidentes; se houver um incidente relevante, avisaremos você e a ANPD.

9. Cookies
Usamos apenas cookies essenciais para manter a sua sessão e proteger os formulários. Não usamos cookies de publicidade.

10. Alterações
Podemos atualizar esta política. A versão e a data em vigor aparecem no topo da página e, em caso de mudança relevante, avisaremos você.
TEXT,

];
