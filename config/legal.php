<?php

return [

    'company' => [
        // Preencher quando a empresa estiver constituída: aparece no rodapé (© e CNPJ).
        'legal_name' => env('LEGAL_COMPANY_NAME'),
        'cnpj' => env('LEGAL_COMPANY_CNPJ'),
    ],

    'support_email' => env('MAIL_SUPPORT_ADDRESS', env('MAIL_REPLY_TO_ADDRESS', 'suporte@revisalog.com.br')),

    'terms_version' => '2026-09-21',

    'privacy_version' => '2026-09-22',

    'terms_of_use' => <<<'TEXT'
Termos de Uso — Revisalog

Estes termos regem o uso da plataforma Revisalog (revisalog.com.br), que registra o histórico de manutenções vinculado ao veículo (chassi/VIN), e não à conta do proprietário.

1. Responsabilidade pelas informações
Ao cadastrar veículos, quilometragens, datas de manutenção, notas fiscais e demais dados, você declara que as informações são verdadeiras e de sua responsabilidade como proprietário ou representante autorizado.

2. Quilometragem e datas
A quilometragem informada deve refletir o hodômetro do veículo no momento do registro. Manutenções devem ser registradas com a quilometragem e a data corretas. O sistema utiliza esses dados para compor o histórico do veículo.

3. Isenção de responsabilidade
A Revisalog atua como repositório do histórico informado pelos usuários. Não verificamos, em regra, a veracidade dos dados, não garantimos a completude do histórico e não nos responsabilizamos por informações falsas, incompletas ou desatualizadas fornecidas por terceiros. Selos de oficina identificam registros feitos pela oficina cadastrada; registros declarados pelo dono ou lojista não passam por essa verificação.

4. Uso do histórico
O histórico exportado ou consultado reflete exclusivamente o que foi registrado na plataforma. Decisões de compra, venda, revisão ou garantia devem considerar fontes adicionais e inspeção presencial quando aplicável.

5. Notas fiscais e documentos
Documentos enviados (NF-e, DANFE, CRLV) são de responsabilidade de quem os anexa. O parse automático pode conter imprecisões; confira sempre os dados antes de salvar.

6. Contato
Dúvidas, solicitações e reclamações: suporte@revisalog.com.br ou a página de contato em revisalog.com.br/contato.

7. Aceite
Ao marcar que leu e aceita estes termos, você confirma ciência das condições acima e concorda em mantê-las atualizadas conforme alterações futuras publicadas nesta versão.
TEXT,

    'privacy_policy' => <<<'TEXT'
Política de Privacidade — Revisalog

Esta política descreve como a Revisalog (revisalog.com.br) trata dados pessoais, para quê e quais são os seus direitos, em linha com a Lei Geral de Proteção de Dados (LGPD, Lei nº 13.709/2018).

1. Controlador e contato
O tratamento é feito pela Revisalog, identificada no rodapé do site. Para exercer seus direitos ou tirar dúvidas sobre privacidade, escreva para suporte@revisalog.com.br ou use a página revisalog.com.br/contato com o assunto "Privacidade e dados pessoais (LGPD)".

2. Dados que coletamos
- Conta: nome, e-mail, senha (armazenada de forma irreversível), telefone e CPF/CNPJ quando informados e, quando você entra com Google, Facebook ou X, o identificador fornecido por esse provedor.
- Endereço: CEP, logradouro, cidade e estado, e as coordenadas geográficas derivadas do endereço (usadas para localizar oficinas).
- Veículos e histórico: chassi, placa, RENAVAM, marca, modelo, ano, quilometragem, manutenções, fotos, garantias, notas fiscais (NF-e/DANFE) e CRLV enviados por você.
- Uso do serviço: notificações (e-mail e no aplicativo), tokens de dispositivo para aviso push e registros técnicos de acesso (IP, data e hora), mantidos por 6 meses conforme o Marco Civil da Internet.
- Contato: nome, e-mail e mensagem enviados pela página de contato.

3. Para que usamos
- Prestar o serviço: manter o histórico de manutenções vinculado ao veículo, gerar relatórios em PDF e permitir a consulta por placa, chassi ou RENAVAM (execução de contrato).
- Segurança: autenticação, verificação em duas etapas, prevenção de fraude e de abuso (legítimo interesse).
- Comunicação: lembretes de manutenção, avisos de garantia e respostas a solicitações (execução de contrato e legítimo interesse).
- Cumprir obrigações legais e regulatórias, incluindo a guarda de registros de acesso.

4. O histórico fica com o veículo
O histórico de manutenções é vinculado ao veículo (chassi/VIN), não ao proprietário. Quando o veículo muda de dono, o novo proprietário passa a ver os registros de manutenção anteriores, mas não os seus dados pessoais de cadastro (nome, e-mail, telefone, documento ou endereço).

5. Compartilhamento
Não vendemos seus dados. Compartilhamos apenas o necessário com:
- Oficinas e lojistas, que veem os dados do veículo e das manutenções no contexto do serviço;
- Prestadores de infraestrutura que operam em nosso nome: Laravel Cloud (hospedagem), Cloudflare (rede, DNS, armazenamento de arquivos e recebimento de e-mail), Resend (envio de e-mail), Google Firebase (notificações push) e OpenStreetMap/Nominatim (geolocalização de endereços);
- Autoridades públicas, quando exigido por lei ou ordem judicial.
Alguns desses prestadores armazenam dados fora do Brasil, com as salvaguardas previstas no art. 33 da LGPD.

6. Conservação
Mantemos os dados enquanto a conta estiver ativa e o histórico do veículo for necessário ao serviço, ou pelo prazo exigido por lei. Você pode excluir a conta no aplicativo (Configurações → Excluir conta) ou pelo e-mail de suporte. Ao excluir a conta, apagamos ou anonimizamos os dados pessoais, exceto os que precisamos manter por obrigação legal. Os registros de manutenção continuam vinculados ao veículo, sem identificar você.

7. Seus direitos
Você pode solicitar confirmação de tratamento, acesso, correção, anonimização, bloqueio ou eliminação, portabilidade, informação sobre compartilhamentos e revogação de consentimento, quando aplicável. No aplicativo, a exclusão da conta está em Configurações → Excluir conta. Envie o pedido para suporte@revisalog.com.br; respondemos em até 15 dias. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).

8. Segurança
Usamos conexão criptografada (HTTPS), senhas armazenadas de forma irreversível, verificação em duas etapas e controle de acesso por perfil. Nenhum sistema é isento de risco; se houver um incidente relevante, avisaremos você e a ANPD. Evite reutilizar senhas e proteja o acesso à sua conta.

9. Cookies
Usamos apenas cookies essenciais para manter a sua sessão e proteger os formulários. Não usamos cookies de publicidade.

10. Alterações
Esta política pode ser atualizada. A versão vigente, com a data, é a publicada em revisalog.com.br/privacidade; em caso de mudança relevante, avisaremos você.
TEXT,

];
