-- Script SQL para crear una partida sin terminar para el usuario "Agustin" (ID=3) contra "player1" (ID=1)

-- Insertar partida sin terminar
INSERT INTO games (game_id, player1_user_id, player2_user_id, created_at, finished_at, status, current_round, current_turn, active_seat)
VALUES (100, 1, 3, NOW(), NULL, 'IN_PROGRESS', 1, 2, 0);

-- Crear registros de bolsas para ambos jugadores
-- Bolsa del jugador 1 (player1)
INSERT INTO bags (bag_id, game_id, user_id)
VALUES (100, 100, 1);

-- Bolsa del jugador 2 (Agustin)
INSERT INTO bags (bag_id, game_id, user_id)
VALUES (101, 100, 3);

-- Insertar dinosaurios en la bolsa del jugador 1 (player1)
INSERT INTO bag_contents (bag_content_id, bag_id, species_id, is_played) VALUES
(1001, 100, 1, 0), -- T (Tiranosaurio)
(1002, 100, 2, 0), -- D (Diplodocus)
(1003, 100, 3, 0), -- D (Diplodocus)
(1004, 100, 4, 0), -- S (Spinosaurus)
(1005, 100, 5, 0), -- B (Braquiosaurio)
(1006, 100, 6, 0); -- A (Anquilosaurio)

-- Insertar dinosaurios en la bolsa del jugador 2 (Agustin)
INSERT INTO bag_contents (bag_content_id, bag_id, species_id, is_played) VALUES
(2001, 101, 4, 0), -- S (Spinosaurus)
(2002, 101, 1, 0), -- T (Tiranosaurio)
(2003, 101, 5, 0), -- B (Braquiosaurio)
(2004, 101, 2, 0), -- D (Diplodocus)
(2005, 101, 6, 0), -- A (Anquilosaurio)
(2006, 101, 3, 0); -- D (Diplodocus)

-- Insertar algunas tiradas de dados de ronda para simular el progreso de la partida

-- Insertar colocaciones para ambos jugadores en las rondas anteriores
-- Colocaciones del jugador 1 (player1) - Ronda 1
-- Marcamos el dinosaurio T como jugado
UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = 1001;
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at)
VALUES (10001, 100, 1001, 1, 0, 0, DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- Colocaciones del jugador 1 (player1) - Ronda 2
-- Marcamos el dinosaurio D como jugado
UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = 1002;
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at)
VALUES (10002, 100, 1002, 2, 0, 1, DATE_SUB(NOW(), INTERVAL 12 MINUTE));

-- Colocaciones del jugador 2 (Agustin) - Ronda 1
-- Marcamos el dinosaurio S como jugado
UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = 2001;
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at)
VALUES (10003, 100, 2001, 3, 1, 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE));

-- Colocaciones del jugador 2 (Agustin) - Ronda 2
-- Marcamos el dinosaurio T como jugado
UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = 2002;
INSERT INTO placement (placement_id, game_id, dino_id, enclosures_id, player_seat, slot_index, placed_at)
VALUES (10004, 100, 2002, 4, 1, 1, DATE_SUB(NOW(), INTERVAL 8 MINUTE));

-- Insertar puntuaciones parciales para las rondas completadas
-- Puntuación del jugador 1 (player1)
INSERT INTO final_score (game_id, player_seat, total_points, river_points, trex_bonus_points, tiebreaker_trex_count, created_at)
VALUES (100, 0, 5, 2, 1, 1, NOW());

-- Puntuación del jugador 2 (Agustin)
INSERT INTO final_score (game_id, player_seat, total_points, river_points, trex_bonus_points, tiebreaker_trex_count, created_at)
VALUES (100, 1, 4, 1, 1, 0, NOW());

select * from placement;