<?php

return [

    'support_email' => env('MAIL_SUPPORT_ADDRESS', env('MAIL_REPLY_TO_ADDRESS', 'suporte@revisalog.com.br')),

    'terms_version' => '2026-09-21',

    'privacy_version' => '2026-09-21',

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

Esta política descreve como a Revisalog (revisalog.com.br) trata dados pessoais, em linha com a Lei Geral de Proteção de Dados (LGPD, Lei nº 13.709/2018).

1. Controlador e contato
O tratamento é feito pela Revisalog. Para exercer seus direitos ou tirar dúvidas: suporte@revisalog.com.br.

2. Dados que coletamos
- Conta: nome, e-mail, senha (armazenada de forma irreversível), telefone e CPF/CNPJ quando informados.
- Veículos e histórico: chassi, placa, RENAVAM, quilometragem, manutenções, fotos, notas fiscais (NF-e/DANFE) e CRLV enviados por você.
- Uso do serviço: notificações (e-mail e no aplicativo), tokens de dispositivo para aviso push, e registros técnicos necessários para segurança e funcionamento.

3. Para que usamos
Prestar o serviço de histórico do veículo, autenticar o acesso, enviar relatórios e notificações que você solicita ou configura, melhorar a plataforma e cumprir obrigações legais.

4. Compartilhamento
Oficinas e lojistas veem os dados do veículo e das manutenções no contexto do serviço. Não vendemos seus dados. Prestadores de infraestrutura (hospedagem, armazenamento de arquivos e envio de e-mail) tratam dados só para operar a plataforma.

5. Conservação
Mantemos os dados enquanto a conta e o histórico do veículo forem necessários ao serviço, ou pelo prazo exigido por lei. Você pode pedir correção ou exclusão pelo e-mail de suporte, ressalvadas obrigações legais de retenção.

6. Seus direitos
Você pode solicitar confirmação de tratamento, acesso, correção, anonimização, portabilidade e eliminação dos dados, além de informação sobre compartilhamentos e revogação de consentimento, quando aplicável.

7. Segurança
Adotamos medidas técnicas e organizacionais razoáveis. Nenhum sistema é isento de risco; evite reutilizar senhas e proteja o acesso à sua conta.

8. Alterações
Esta política pode ser atualizada. A versão vigente é a publicada em revisalog.com.br/privacidade.
TEXT,

];
