-- 02-seed-series.sql
-- Cria a tabela "series" e popula com dados fictícios.
-- Arquivos .sql em /docker-entrypoint-initdb.d/ são executados diretamente
-- pelo MySQL contra o banco definido em MYSQL_DATABASE, na ordem alfabética.

CREATE TABLE IF NOT EXISTS series (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao VARCHAR(255),
    ano_lancamento INT,
    diretor VARCHAR(255),
    genero VARCHAR(100),
    temporadas INT,
    duracao INT
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO series (titulo, descricao, ano_lancamento, diretor, genero, temporadas, duracao) VALUES
('Fronteiras do Silêncio', 'Um detetive investiga desaparecimentos em uma cidade isolada.', 2019, 'Marina Costa', 'Suspense', 3, 45),
('Código Vermelho', 'Hackers tentam impedir um ataque cibernético global.', 2022, 'Ricardo Alves', 'Ficção Científica', 2, 50),
('Doce Ilusão', 'Uma confeiteira reconstrói a vida após um divórcio.', 2018, 'Patrícia Nunes', 'Comédia', 4, 30),
('Última Fronteira', 'Sobreviventes tentam recolonizar a Terra após um colapso ambiental.', 2024, 'Eduardo Farias', 'Drama', 1, 55),
('Sombras do Passado', 'Uma família esconde segredos que vêm à tona após uma morte.', 2020, 'Camila Souza', 'Drama', 5, 48),
('Risada Fácil', 'Um grupo de amigos administra um bar em crise.', 2021, 'Bruno Lima', 'Comédia', 3, 25),
('Zona de Impacto', 'Uma equipe de resgate atua em desastres naturais pelo mundo.', 2023, 'Felipe Rocha', 'Ação', 2, 42),
('Herança Maldita', 'Irmãos disputam uma mansão assombrada pela morte do pai.', 2017, 'Renata Dias', 'Terror', 1, 40),
('Vidas Paralelas', 'Duas pessoas trocam de vida em universos alternativos.', 2025, 'Diego Martins', 'Ficção Científica', 2, 52),
('Café com Segredos', 'Uma jornalista investiga corrupção em uma pequena cidade.', 2016, 'Larissa Pinto', 'Suspense', 4, 44);