-- Script SQL para crear una partida sin terminar para el usuario "Agustin" (ID=3) contra "player1" (ID=1)

-- Insertar partida sin terminar
INSERT INTO games (game_id, player1_user_id, player2_user_id, created_at, finished_at, status, current_round, current_turn, active_seat)
VALUES (100, 3, 1, NOW(), NULL, 'IN_PROGRESS', 1, 0, 0);

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

