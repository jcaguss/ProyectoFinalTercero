-- Creación de la base de datos Draftosaurus
CREATE DATABASE IF NOT EXISTS draftosaurus;
USE draftosaurus;
-- drop database draftosaurus;
-- Eliminación de tablas existentes si fuera necesario (comentar si no se desea eliminar)
-- SET FOREIGN_KEY_CHECKS = 0;
-- DROP TABLE IF EXISTS final_score;
-- DROP TABLE IF EXISTS placement;
-- DROP TABLE IF EXISTS placement_die_rolls;
-- DROP TABLE IF EXISTS bag_contents;
-- DROP TABLE IF EXISTS bags;
-- DROP TABLE IF EXISTS enclosures;
-- DROP TABLE IF EXISTS species;
-- DROP TABLE IF EXISTS games;
-- DROP TABLE IF EXISTS users;
-- SET FOREIGN_KEY_CHECKS = 1;

-- Creación de tablas
CREATE TABLE users (
  user_id        BIGINT PRIMARY KEY AUTO_INCREMENT,
  username       VARCHAR(50) NOT NULL UNIQUE,
  email          VARCHAR(120) NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('PLAYER','ADMIN') DEFAULT 'PLAYER' NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE games (
  game_id        BIGINT PRIMARY KEY AUTO_INCREMENT,
  status         ENUM('IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'IN_PROGRESS',
  player1_user_id BIGINT NULL,
  player2_user_id BIGINT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at    TIMESTAMP NULL,
  turn_started_at TIMESTAMP NULL,
  current_round TINYINT NOT NULL DEFAULT 1,
  current_turn INT DEFAULT 0,
  active_seat TINYINT DEFAULT 0,
  CONSTRAINT fk_game_p1_user FOREIGN KEY (player1_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_game_p2_user FOREIGN KEY (player2_user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE species (
    species_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) NOT NULL DEFAULT 'unknown',
    img VARCHAR(100) NOT NULL
);

CREATE TABLE bags (
  bag_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  game_id BIGINT NOT NULL,
  user_id BIGINT,
  FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE bag_contents (
  bag_content_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  bag_id BIGINT NOT NULL,
  species_id BIGINT NOT NULL,
  is_played BOOLEAN NOT NULL DEFAULT 0,  -- 0 = en bolsa, 1 = ya jugado
  FOREIGN KEY (bag_id) REFERENCES bags(bag_id) ON DELETE CASCADE,
  FOREIGN KEY (species_id) REFERENCES species(species_id)
);

CREATE TABLE enclosures(
  enclosures_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name_enclosures VARCHAR(50) NOT NULL,
  position ENUM('left', 'right', 'center') NOT NULL DEFAULT 'center',
  terrain ENUM('forest', 'rock', 'mixed') NOT NULL DEFAULT 'mixed',
  special_rule ENUM(
      'SAME_SPECIES',      
      'DIFFERENT_SPECIES', 
      'PAIRS_BONUS',       
      'TRIO_REQUIRED',     
      'MAJORITY_SPECIES',  
      'UNIQUE_SPECIES',    
      'NO_RESTRICTIONS'    
  ) NOT NULL DEFAULT 'NO_RESTRICTIONS',
  max_dinos	  INT NOT NULL	
);

CREATE TABLE placement (
  placement_id   BIGINT PRIMARY KEY AUTO_INCREMENT,
  game_id        BIGINT NOT NULL,
  dino_id        BIGINT NOT NULL,   -- referencia a bag_contents
  enclosures_id  BIGINT NOT NULL,   -- referencia a recintos
  player_seat    TINYINT NOT NULL,  -- 0 = player1, 1 = player2
  slot_index     TINYINT NULL,
  placed_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pl_game FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_dino FOREIGN KEY (dino_id) REFERENCES bag_contents(bag_content_id) ON DELETE CASCADE,
  CONSTRAINT fk_pl_enclosures FOREIGN KEY (enclosures_id) REFERENCES enclosures(enclosures_id) ON DELETE CASCADE,
  UNIQUE KEY uq_enclosure_slot (game_id, player_seat, enclosures_id, slot_index)
);

CREATE TABLE final_score (
  game_id             BIGINT NOT NULL,
  player_seat         TINYINT NOT NULL,  -- 0 o 1
  total_points        SMALLINT NOT NULL,
  river_points        SMALLINT NOT NULL,
  trex_bonus_points   SMALLINT NOT NULL,
  tiebreaker_trex_count SMALLINT NOT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (game_id, player_seat),
  CONSTRAINT fk_fs_game FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
);

CREATE TABLE placement_die_rolls (
  roll_id BIGINT PRIMARY KEY AUTO_INCREMENT,
  game_id BIGINT NOT NULL,
  affected_player_seat TINYINT NOT NULL,  -- 0 o 1
  die_face ENUM(
      'LEFT_SIDE',
      'RIGHT_SIDE',
      'FOREST',
      'EMPTY',
      'NO_TREX',
      'ROCKS'
  ) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rolls_game FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
);

-- Índices para mejorar rendimiento
CREATE INDEX ix_placement_board ON placement (game_id, player_seat, enclosures_id);

-- Insertar usuarios de prueba
INSERT INTO users (username, email, password_hash, role) VALUES 
('player1', 'player1@example.com', '$2y$10$qDJwUg/0K2T0tUbLOauL0.IVyKXgTJYT3EsEMz9LxVMgfYlnkWkXy', 'PLAYER'),
('player2', 'player2@example.com', '$2y$10$qDJwUg/0K2T0tUbLOauL0.IVyKXgTJYT3EsEMz9LxVMgfYlnkWkXy', 'PLAYER');

-- Insertar especies de dinosaurios
INSERT INTO species (species_id, name, code, img) VALUES
(1, 'Triceratops Amarillo', 'amarillo', './img/amarilloHori.PNG'),
(2, 'T-Rex Rojo', 'rojo', './img/rojoHori.PNG'),
(3, 'Estegosaurio Verde', 'verde', './img/verdeHori.PNG'),
(4, 'Diplodocus Azul', 'azul', './img/azulHori.PNG'),
(5, 'Alosaurio Rosa', 'rosa', './img/rosaHori.PNG'),
(6, 'Velociraptor Naranja', 'naranja', './img/naranjaHori.PNG');

-- Insertar recintos
INSERT INTO enclosures (enclosures_id, name_enclosures, position, terrain, special_rule, max_dinos) VALUES
(1, 'Bosque de Semejanza', 'left', 'forest', 'SAME_SPECIES', 6),
(2, 'Parado Diferencia', 'left', 'rock', 'DIFFERENT_SPECIES', 6),
(3, 'Pradera del Amor', 'right', 'mixed', 'PAIRS_BONUS', 6),
(4, 'Trio Frondoso', 'center', 'forest', 'TRIO_REQUIRED', 3),
(5, 'Rey de la Selva', 'right', 'forest', 'MAJORITY_SPECIES', 1),
(6, 'Isla Solitaria', 'center', 'rock', 'UNIQUE_SPECIES', 1),
(7, 'Rio', 'center', 'mixed', 'NO_RESTRICTIONS', 6);

-- Crear una partida en progreso
INSERT INTO games (game_id, status, player1_user_id, player2_user_id, created_at, turn_started_at, current_round, current_turn, active_seat)
VALUES (1, 'IN_PROGRESS', 1, 2, NOW(), NOW(), 1, 3, 0);

-- Crear bolsas para ambos jugadores
INSERT INTO bags (bag_id, game_id, user_id) VALUES
(1, 1, 1), -- Bolsa del jugador 1
(2, 1, 2); -- Bolsa del jugador 2

-- Insertar dinosaurios en la bolsa del jugador 1
INSERT INTO bag_contents (bag_content_id, bag_id, species_id, is_played) VALUES
(1, 1, 1, 0), -- Amarillo disponible
(2, 1, 2, 0), -- Rojo disponible
(3, 1, 3, 0), -- Verde disponible
(4, 1, 4, 1), -- Azul ya jugado
(5, 1, 5, 0), -- Rosa disponible
(6, 1, 6, 1); -- Naranja ya jugado

-- Insertar dinosaurios en la bolsa del jugador 2
INSERT INTO bag_contents (bag_content_id, bag_id, species_id, is_played) VALUES
(7, 2, 1, 0),  -- Amarillo disponible
(8, 2, 2, 0),  -- Rojo disponible
(9, 2, 3, 0),  -- Verde disponible
(10, 2, 4, 0), -- Azul disponible
(11, 2, 5, 1), -- Rosa ya jugado
(12, 2, 6, 0); -- Naranja disponible

-- Registrar colocaciones de dinosaurios ya realizadas
-- Jugador 1 ha colocado 2 dinosaurios
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at) VALUES
(1, 1, 4, 1, 0, 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE)), -- Azul en Bosque de Semejanza
(2, 1, 6, 3, 0, 0, DATE_SUB(NOW(), INTERVAL 5 MINUTE));  -- Naranja en Pradera del Amor

-- Jugador 2 ha colocado 1 dinosaurio
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at) VALUES
(3, 1, 11, 7, 1, 0, DATE_SUB(NOW(), INTERVAL 8 MINUTE)); -- Rosa en Río

-- Registrar tiradas de dado
INSERT INTO placement_die_rolls (roll_id, game_id, affected_player_seat, die_face, created_at) VALUES
(1, 1, 0, 'EMPTY', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),     -- Primera tirada (player 1)
(2, 1, 1, 'FOREST', DATE_SUB(NOW(), INTERVAL 12 MINUTE)),    -- Segunda tirada (player 2)
(3, 1, 0, 'LEFT_SIDE', DATE_SUB(NOW(), INTERVAL 7 MINUTE));  -- Tercera tirada (player 1 actual)

-- Procedimiento almacenado para colocar un dinosaurio
DELIMITER $$
DROP PROCEDURE IF EXISTS place_dinosaur$$
CREATE PROCEDURE place_dinosaur(
    IN game_id_param BIGINT,
    IN dino_id_param BIGINT,
    IN enclosure_id_param BIGINT,
    IN player_seat_param TINYINT,
    OUT success BOOLEAN
)
BEGIN
    DECLARE slot_index_value TINYINT;
    DECLARE dino_exists BOOLEAN DEFAULT FALSE;
    
    -- Verificar que el dinosaurio existe y pertenece a la bolsa del jugador
    SELECT TRUE INTO dino_exists
    FROM bag_contents bc
    JOIN bags b ON bc.bag_id = b.bag_id
    WHERE bc.bag_content_id = dino_id_param 
      AND b.game_id = game_id_param
      AND bc.is_played = 0;
    
    -- Si el dinosaurio existe y está disponible
    IF dino_exists THEN
        -- Calcular el siguiente índice de slot disponible
        SELECT IFNULL(MAX(slot_index) + 1, 0) INTO slot_index_value
        FROM placement
        WHERE game_id = game_id_param 
          AND player_seat = player_seat_param 
          AND enclosures_id = enclosure_id_param;
        
        -- Insertar la colocación
        INSERT INTO placement (game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at)
        VALUES (game_id_param, dino_id_param, enclosure_id_param, player_seat_param, slot_index_value, NOW());
        
        -- Marcar el dinosaurio como jugado
        UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = dino_id_param;
        
        -- Incrementar el turno actual del juego
        UPDATE games SET current_turn = current_turn + 1 WHERE game_id = game_id_param;
        
        SET success = TRUE;
    ELSE
        SET success = FALSE;
    END IF;
END$$
DELIMITER ;

-- Procedimiento almacenado para obtener el estado completo del juego
DELIMITER //
DROP PROCEDURE IF EXISTS get_game_state//
CREATE PROCEDURE get_game_state(IN game_id_param BIGINT)
BEGIN
    -- Información básica del juego
    SELECT 
        g.game_id, 
        g.status, 
        g.player1_user_id, 
        g.player2_user_id, 
        u1.username AS player1_username, 
        u2.username AS player2_username,
        g.current_round,
        g.current_turn,
        g.active_seat,
        UNIX_TIMESTAMP(g.turn_started_at) AS turn_started_at_unix
    FROM 
        games g
    JOIN users u1 ON g.player1_user_id = u1.user_id
    JOIN users u2 ON g.player2_user_id = u2.user_id
    WHERE 
        g.game_id = game_id_param;
    
    -- Dinosaurios en bolsas de jugadores
    SELECT 
        b.user_id,
        bc.bag_content_id,
        bc.species_id AS dino_type,
        s.code AS dino_code,
        bc.is_played
    FROM 
        bags b
    JOIN bag_contents bc ON b.bag_id = bc.bag_id
    JOIN species s ON bc.species_id = s.species_id
    WHERE 
        b.game_id = game_id_param;
    
    -- Colocaciones en recintos
    SELECT 
        e.enclosures_id,
        e.name_enclosures AS name,
        e.special_rule,
        p.placement_id,
        p.player_seat,
        p.slot_index,
        bc.species_id AS dino_type,
        s.code AS dino_code
    FROM 
        enclosures e
    LEFT JOIN placement p ON e.enclosures_id = p.enclosures_id AND p.game_id = game_id_param
    LEFT JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
    LEFT JOIN species s ON bc.species_id = s.species_id;
    
    -- Última tirada de dado
    SELECT 
        r.roll_id,
        r.affected_player_seat,
        r.die_face,
        UNIX_TIMESTAMP(r.created_at) AS roll_time
    FROM 
        placement_die_rolls r
    WHERE 
        r.game_id = game_id_param
    ORDER BY 
        r.created_at DESC
    LIMIT 1;
END//
DELIMITER ;
select * from placement;
select * from games;
