<!DOCTYPE html>
<html lang="fa">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>شطرنج نسخه مینی اَپ | بازوپرداز</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            overflow: clip;
        }

        body {
            background: radial-gradient(circle at 20% 30%, #0a0a1a, #010005);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            font-family: 'Segoe UI', system-ui, -apple-system, 'Orbitron', monospace;
            position: relative;
        }

        .star-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .star {
            position: absolute;
            background-color: #fff;
            border-radius: 50%;
            opacity: 0;
            animation: twinkle 3s infinite ease-in-out;
        }

        @keyframes twinkle {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }

            20% {
                opacity: 0.8;
                transform: scale(1);
            }

            40% {
                opacity: 0.3;
            }

            60% {
                opacity: 0.9;
                transform: scale(1.1);
            }

            80% {
                opacity: 0.4;
            }

            100% {
                opacity: 0;
                transform: scale(0.6);
            }
        }

        .game-container {
            background: rgba(8, 8, 20, 0.4);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            padding: 10px 12px 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 0 0 1.5px rgba(0, 255, 255, 0.4);
            z-index: 2;
            width: min(94%, calc(100vh - 90px));
            max-width: 560px;
            display: flex;
            flex-direction: column;
            margin: auto;
        }

        .chessboard {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            background: #0a0a1a;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 0 0 2px #0ff, 0 0 0 5px rgba(100, 0, 255, 0.3);
            width: 100%;
            aspect-ratio: 1 / 1;
            position: relative;
        }

        .square {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.5rem, 7vw, 2.8rem);
            font-weight: 500;
            cursor: pointer;
            border: 0.5px solid rgba(0, 255, 255, 0.2);
            aspect-ratio: 1 / 1;
            transition: all 0.08s;
        }

        .square.light {
            background: radial-gradient(circle at 30% 30%, rgba(25, 60, 95, 0.95), rgba(10, 35, 70, 0.87));
        }

        .square.dark {
            background: radial-gradient(circle at 70% 70%, rgba(5, 15, 40, 0.98), rgba(0, 5, 25, 0.98));
        }

        /* رنگ مهره‌ها - نهایی */
        .piece-white {
            color: #ffffffce;
            text-shadow: 0 0 2px rgba(0, 255, 255, 0.25);
        }

        .piece-black {
            color: #000000;
            text-shadow: 0 0 4px rgba(255, 255, 255, 0.74);
        }

        .square.selected {
            box-shadow: inset 0 0 0 3px cyan, 0 0 12px rgba(0, 255, 255, 0.9);
            background: rgba(0, 180, 230, 0.35);
            transform: scale(0.97);
        }

        .square.legal-move {
            box-shadow: inset 0 0 0 2px #f0f, 0 0 10px magenta;
            background: radial-gradient(circle, rgba(255, 0, 255, 0.25), rgba(0, 0, 0, 0.5));
            animation: pulseLegal 0.7s infinite;
        }

        @keyframes pulseLegal {
            0% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        .check-glow {
            animation: checkPulse 0.4s ease-in-out 3;
        }

        @keyframes checkPulse {
            0% {
                text-shadow: 0 0 0 red;
            }

            50% {
                text-shadow: 0 0 20px red, 0 0 8px orange;
            }

            100% {
                text-shadow: 0 0 6px cyan;
            }
        }

        .floating-piece {
            position: fixed;
            z-index: 1000;
            font-size: clamp(1.5rem, 7vw, 2.8rem);
            font-weight: 500;
            text-shadow: 0 0 8px cyan, 0 0 12px #0ff;
            pointer-events: none;
            filter: drop-shadow(0 0 6px rgba(0, 255, 255, 0.8));
            transition: left 0.07s linear, top 0.07s linear, transform 0.2s ease, opacity 0.2s;
            will-change: left, top, transform, opacity;
            contain: layout style;
        }

        .info-panel {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(12px);
            padding: 6px 12px;
            border-radius: 60px;
            border: 1px solid #0ff;
            gap: 8px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .turn-indicator,
        .status-message {
            background: #00000080;
            padding: 4px 12px;
            border-radius: 40px;
            font-weight: bold;
            color: #0ff;
            font-size: 0.8rem;
        }

        .status-message {
            color: #eeddff;
            background: #0a0a2e;
            flex: 1;
            text-align: center;
        }

        button {
            background: linear-gradient(90deg, #1a1a3a, #0a0a2a);
            border: 1px solid #0ff;
            color: cyan;
            padding: 5px 16px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: bold;
            transition: 0.2s;
        }

        button:active {
            transform: scale(0.97);
        }

        /* ========== بهینه‌سازی ویژه موبایل ========== */
        @media (max-width: 550px) {
            .square {
                font-size: clamp(1.2rem, 6vw, 2.2rem);
            }

            .floating-piece {
                font-size: clamp(1.2rem, 6vw, 2.2rem);
            }

            .game-container {
                width: min(96%, calc(100vh - 80px));
                padding: 8px 8px 8px;
                border-radius: 22px;
                margin: auto;
            }

            .chessboard {
                border-radius: 12px;
            }

            .info-panel {
                margin-top: 8px;
                padding: 4px 8px;
                gap: 5px;
                border-radius: 40px;
                flex-wrap: nowrap;
            }

            .turn-indicator,
            .status-message,
            button {
                font-size: 0.65rem;
                padding: 3px 8px;
                white-space: nowrap;
            }

            .status-message {
                flex: 0 1 auto;
                max-width: 45%;
                overflow-x: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
        }

        @media (max-width: 400px) {
            .game-container {
                width: min(98%, calc(100vh - 70px));
                padding: 6px 6px 6px;
                border-radius: 20px;
                margin: auto;
            }

            .chessboard {
                border-radius: 10px;
            }

            .turn-indicator,
            .status-message,
            button {
                font-size: 0.6rem;
                padding: 2px 6px;
            }

            .status-message {
                max-width: 40%;
            }
        }
    </style>
</head>

<body>
    <div class="star-bg" id="starBg"></div>
    <div class="game-container">
        <div class="chessboard" id="chessboard"></div>
        <div class="info-panel">
            <div class="turn-indicator" id="turnText">نوبت شما (سفید)</div>
            <div class="status-message" id="statusMsg">یک مهره را انتخاب کن</div>
            <button id="resetBtn">🔄 بازی جدید</button>
        </div>
    </div>

    <script>
        // ======================== هسته شطرنج (کاملاً مستقل) ========================
        const WHITE = 'white';
        const BLACK = 'black';

        function getPieceSymbol(piece) {
            if (!piece) return '';
            const symbols = {
                'white_king': '♔', 'white_queen': '♕', 'white_rook': '♖', 'white_bishop': '♗', 'white_knight': '♘', 'white_pawn': '♙',
                'black_king': '♚', 'black_queen': '♛', 'black_rook': '♜', 'black_bishop': '♝', 'black_knight': '♞', 'black_pawn': '♟'
            };
            return symbols[`${piece.color}_${piece.type}`] || '';
        }

        let board = Array(8).fill().map(() => Array(8).fill(null));

        function initBoard() {
            for (let i = 0; i < 8; i++) for (let j = 0; j < 8; j++) board[i][j] = null;
            for (let i = 0; i < 8; i++) {
                board[1][i] = { type: 'pawn', color: BLACK };
                board[6][i] = { type: 'pawn', color: WHITE };
            }
            const backBlack = ['rook', 'knight', 'bishop', 'queen', 'king', 'bishop', 'knight', 'rook'];
            const backWhite = ['rook', 'knight', 'bishop', 'queen', 'king', 'bishop', 'knight', 'rook'];
            for (let i = 0; i < 8; i++) {
                board[0][i] = { type: backBlack[i], color: BLACK };
                board[7][i] = { type: backWhite[i], color: WHITE };
            }
        }

        let kingPos = { white: { row: 7, col: 4 }, black: { row: 0, col: 4 } };
        let turn = WHITE;
        let gameOver = false;
        let winner = null;
        let selectedRow = null, selectedCol = null, legalMoves = [];

        function isInside(r, c) { return r >= 0 && r < 8 && c >= 0 && c < 8; }

        function isSquareAttacked(board, row, col, defendingColor) {
            const attackerColor = defendingColor === WHITE ? BLACK : WHITE;
            for (let i = 0; i < 8; i++) for (let j = 0; j < 8; j++) {
                const p = board[i][j];
                if (p && p.color === attackerColor) {
                    const moves = getPieceMoves(board, i, j, p);
                    for (let mv of moves) if (mv.row === row && mv.col === col) return true;
                }
            }
            return false;
        }

        function getPieceMoves(board, row, col, piece) {
            const moves = [];
            const color = piece.color;
            switch (piece.type) {
                case 'pawn': {
                    const dir = color === WHITE ? -1 : 1;
                    const startRow = color === WHITE ? 6 : 1;
                    if (isInside(row + dir, col) && !board[row + dir][col]) {
                        moves.push({ row: row + dir, col: col });
                        if (row === startRow && !board[row + 2 * dir][col]) moves.push({ row: row + 2 * dir, col: col });
                    }
                    for (let dc of [-1, 1]) {
                        const r = row + dir, c = col + dc;
                        if (isInside(r, c) && board[r][c] && board[r][c].color !== color) moves.push({ row: r, col: c });
                    }
                    break;
                }
                case 'knight': {
                    const dirs = [[-2, -1], [-2, 1], [-1, -2], [-1, 2], [1, -2], [1, 2], [2, -1], [2, 1]];
                    for (let [dr, dc] of dirs) {
                        const r = row + dr, c = col + dc;
                        if (isInside(r, c) && (!board[r][c] || board[r][c].color !== color)) moves.push({ row: r, col: c });
                    }
                    break;
                }
                case 'bishop': {
                    for (let [dr, dc] of [[-1, -1], [-1, 1], [1, -1], [1, 1]]) {
                        let r = row + dr, c = col + dc;
                        while (isInside(r, c)) {
                            if (!board[r][c]) moves.push({ row: r, col: c });
                            else { if (board[r][c].color !== color) moves.push({ row: r, col: c }); break; }
                            r += dr; c += dc;
                        }
                    }
                    break;
                }
                case 'rook': {
                    for (let [dr, dc] of [[-1, 0], [1, 0], [0, -1], [0, 1]]) {
                        let r = row + dr, c = col + dc;
                        while (isInside(r, c)) {
                            if (!board[r][c]) moves.push({ row: r, col: c });
                            else { if (board[r][c].color !== color) moves.push({ row: r, col: c }); break; }
                            r += dr; c += dc;
                        }
                    }
                    break;
                }
                case 'queen': {
                    const dirs = [[-1, -1], [-1, 1], [1, -1], [1, 1], [-1, 0], [1, 0], [0, -1], [0, 1]];
                    for (let [dr, dc] of dirs) {
                        let r = row + dr, c = col + dc;
                        while (isInside(r, c)) {
                            if (!board[r][c]) moves.push({ row: r, col: c });
                            else { if (board[r][c].color !== color) moves.push({ row: r, col: c }); break; }
                            r += dr; c += dc;
                        }
                    }
                    break;
                }
                case 'king': {
                    for (let dr = -1; dr <= 1; dr++) for (let dc = -1; dc <= 1; dc++) {
                        if (dr === 0 && dc === 0) continue;
                        const r = row + dr, c = col + dc;
                        if (isInside(r, c) && (!board[r][c] || board[r][c].color !== color)) moves.push({ row: r, col: c });
                    }
                    break;
                }
            }
            return moves;
        }

        function getAllLegalMovesForColor(board, color) {
            const moves = [];
            for (let i = 0; i < 8; i++) for (let j = 0; j < 8; j++) {
                const p = board[i][j];
                if (p && p.color === color) {
                    const pseudo = getPieceMoves(board, i, j, p);
                    for (let mv of pseudo) {
                        const newBoard = copyBoard(board);
                        newBoard[mv.row][mv.col] = newBoard[i][j];
                        newBoard[i][j] = null;
                        let kingRow = kingPos[color].row, kingCol = kingPos[color].col;
                        if (p.type === 'king') { kingRow = mv.row; kingCol = mv.col; }
                        if (!isSquareAttacked(newBoard, kingRow, kingCol, color)) {
                            moves.push({ fromRow: i, fromCol: j, toRow: mv.row, toCol: mv.col, piece: p });
                        }
                    }
                }
            }
            return moves;
        }

        function copyBoard(b) { return b.map(row => row.map(cell => cell ? { ...cell } : null)); }

        function applyMove(move) {
            const piece = board[move.fromRow][move.fromCol];
            board[move.toRow][move.toCol] = piece;
            board[move.fromRow][move.fromCol] = null;
            if (piece.type === 'king') kingPos[piece.color] = { row: move.toRow, col: move.toCol };
        }

        function findKingPosition(color) {
            for (let i = 0; i < 8; i++) for (let j = 0; j < 8; j++) if (board[i][j] && board[i][j].type === 'king' && board[i][j].color === color) return { row: i, col: j };
            return null;
        }

        function checkGameOver() {
            const moves = getAllLegalMovesForColor(board, turn);
            if (moves.length === 0) {
                gameOver = true;
                const kp = findKingPosition(turn);
                if (kp && isSquareAttacked(board, kp.row, kp.col, turn)) winner = turn === WHITE ? BLACK : WHITE;
                else winner = null;
                return true;
            }
            return false;
        }

        // ======================== انیمیشن مسیر با قدم‌های میانی ========================
        function getCellRect(row, col) {
            const container = document.getElementById('chessboard');
            if (!container) return null;
            const idx = row * 8 + col;
            const cell = container.children[idx];
            if (cell) return cell.getBoundingClientRect();
            return null;
        }

        function getStepByStepPath(fromRow, fromCol, toRow, toCol, pieceType) {
            if (pieceType === 'knight' || pieceType === 'king') {
                return [{ row: fromRow, col: fromCol }, { row: toRow, col: toCol }];
            }
            const deltaRow = toRow - fromRow;
            const deltaCol = toCol - fromCol;
            if (deltaRow === 0 && deltaCol === 0) return [{ row: fromRow, col: fromCol }];
            if (Math.abs(deltaRow) !== Math.abs(deltaCol) && deltaRow !== 0 && deltaCol !== 0) {
                return [{ row: fromRow, col: fromCol }, { row: toRow, col: toCol }];
            }
            const stepRow = deltaRow === 0 ? 0 : (deltaRow > 0 ? 1 : -1);
            const stepCol = deltaCol === 0 ? 0 : (deltaCol > 0 ? 1 : -1);
            const steps = Math.max(Math.abs(deltaRow), Math.abs(deltaCol));
            const path = [];
            for (let i = 0; i <= steps; i++) {
                const r = fromRow + stepRow * i;
                const c = fromCol + stepCol * i;
                path.push({ row: r, col: c });
            }
            return path;
        }

        function animatePieceStepwise(fromRow, fromCol, toRow, toCol, pieceSymbol, pieceType, onComplete) {
            const pathCells = getStepByStepPath(fromRow, fromCol, toRow, toCol, pieceType);
            if (pathCells.length === 0) {
                if (onComplete) onComplete();
                return;
            }
            const startRect = getCellRect(fromRow, fromCol);
            if (!startRect) {
                if (onComplete) onComplete();
                return;
            }
            const floating = document.createElement('div');
            floating.className = 'floating-piece';
            floating.textContent = pieceSymbol;
            floating.style.left = startRect.left + startRect.width / 2 + 'px';
            floating.style.top = startRect.top + startRect.height / 2 + 'px';
            floating.style.transform = 'translate(-50%, -50%) scale(1)';
            document.body.appendChild(floating);

            let currentStep = 0;
            const stepDelay = 70;
            function moveToNextStep() {
                if (currentStep >= pathCells.length) {
                    floating.style.opacity = '0';
                    floating.style.transform = 'translate(-50%, -50%) scale(0.9)';
                    setTimeout(() => {
                        floating.remove();
                        if (onComplete) onComplete();
                    }, 150);
                    return;
                }
                const targetCell = pathCells[currentStep];
                const rect = getCellRect(targetCell.row, targetCell.col);
                if (rect) {
                    const targetX = rect.left + rect.width / 2;
                    const targetY = rect.top + rect.height / 2;
                    floating.style.left = targetX + 'px';
                    floating.style.top = targetY + 'px';
                    floating.style.transform = 'translate(-50%, -50%) scale(1.02)';
                    setTimeout(() => {
                        floating.style.transform = 'translate(-50%, -50%) scale(1)';
                    }, 35);
                }
                currentStep++;
                setTimeout(moveToNextStep, stepDelay);
            }
            currentStep = 1;
            setTimeout(moveToNextStep, stepDelay);
        }

        function animateCaptureOnly(row, col, pieceSymbol, onComplete) {
            const rect = getCellRect(row, col);
            if (!rect) {
                if (onComplete) onComplete();
                return;
            }
            const floating = document.createElement('div');
            floating.className = 'floating-piece captured';
            floating.textContent = pieceSymbol;
            floating.style.left = rect.left + rect.width / 2 + 'px';
            floating.style.top = rect.top + rect.height / 2 + 'px';
            floating.style.transform = 'translate(-50%, -50%) scale(1)';
            floating.style.transition = 'transform 0.45s cubic-bezier(0.3, -0.2, 0.8, 1.2), opacity 0.4s';
            document.body.appendChild(floating);

            const angle = Math.random() * Math.PI * 2;
            const distance = 170 + Math.random() * 130;
            const dx = Math.cos(angle) * distance;
            const dy = Math.sin(angle) * distance - 80;
            const randomRotate = Math.random() * 360;

            requestAnimationFrame(() => {
                floating.style.transform = `translate(calc(${dx}px - 50%), calc(${dy}px - 50%)) scale(0.25) rotate(${randomRotate}deg)`;
                floating.style.opacity = '0';
            });
            setTimeout(() => {
                floating.remove();
                if (onComplete) onComplete();
            }, 480);
        }

        function executeMoveWithAnimation(move, afterComplete) {
            const piece = board[move.fromRow][move.fromCol];
            const targetPiece = board[move.toRow][move.toCol];
            const pieceSymbol = getPieceSymbol(piece);
            const isCapture = !!targetPiece;
            const capturedSymbol = isCapture ? getPieceSymbol(targetPiece) : '';
            const pieceType = piece.type;

            const boardDiv = document.getElementById('chessboard');
            boardDiv.style.pointerEvents = 'none';

            let animationsRemaining = isCapture ? 2 : 1;
            function onAnimationDone() {
                animationsRemaining--;
                if (animationsRemaining === 0) {
                    applyMove(move);
                    turn = (turn === WHITE ? BLACK : WHITE);
                    renderBoard();
                    const ended = checkGameOver();
                    updateUI();
                    if (!ended && findKingPosition(turn) && isSquareAttacked(board, findKingPosition(turn).row, findKingPosition(turn).col, turn)) {
                        animateCheckEffect();
                    }
                    boardDiv.style.pointerEvents = 'auto';
                    if (afterComplete) afterComplete(ended);
                }
            }
            if (isCapture) {
                animateCaptureOnly(move.toRow, move.toCol, capturedSymbol, onAnimationDone);
                animatePieceStepwise(move.fromRow, move.fromCol, move.toRow, move.toCol, pieceSymbol, pieceType, onAnimationDone);
            } else {
                animatePieceStepwise(move.fromRow, move.fromCol, move.toRow, move.toCol, pieceSymbol, pieceType, onAnimationDone);
            }
        }

        function animateCheckEffect() {
            const king = findKingPosition(turn);
            if (!king) return;
            const container = document.getElementById('chessboard');
            const idx = king.row * 8 + king.col;
            const cell = container.children[idx];
            if (cell) {
                cell.classList.add('check-glow');
                setTimeout(() => cell.classList.remove('check-glow'), 600);
            }
        }

        // ======================== ربات سیاه ========================
        function getPieceValue(piece) {
            if (!piece) return 0;
            switch (piece.type) {
                case 'pawn': return 10; case 'knight': return 30; case 'bishop': return 30;
                case 'rook': return 50; case 'queen': return 90; case 'king': return 900;
                default: return 0;
            }
        }
        function getCenterBonus(row, col) { const d = Math.abs(3.5 - row) + Math.abs(3.5 - col); return (7 - d) * 1.5; }
        function getPawnAdvanceBonus(row, color) { return color === WHITE ? (7 - row) * 2 : row * 2; }
        function evaluateMove(move, boardState, color) {
            let score = 0;
            const piece = move.piece;
            const targetPiece = boardState[move.toRow][move.toCol];
            if (targetPiece) {
                score += getPieceValue(targetPiece) * 10;
                if (getPieceValue(piece) < getPieceValue(targetPiece)) score += 50;
            }
            if (piece.type === 'pawn' && (move.toRow === 0 || move.toRow === 7)) score += 200;
            if (piece.type === 'knight' || piece.type === 'bishop') score += getCenterBonus(move.toRow, move.toCol);
            if (piece.type === 'pawn') score += getPawnAdvanceBonus(move.toRow, piece.color);
            const testBoard = copyBoard(boardState);
            testBoard[move.toRow][move.toCol] = piece;
            testBoard[move.fromRow][move.fromCol] = null;
            const attackedAfter = isSquareAttacked(testBoard, move.toRow, move.toCol, piece.color);
            if (attackedAfter && !targetPiece) score -= 25;
            const nextTurn = piece.color === WHITE ? BLACK : WHITE;
            const enemyKing = kingPos[nextTurn];
            if (enemyKing && isSquareAttacked(testBoard, enemyKing.row, enemyKing.col, nextTurn)) score += 40;
            return score;
        }
        function getAllLegalMovesWithScore(boardState, color) {
            const moves = getAllLegalMovesForColor(boardState, color);
            return moves.map(m => ({ ...m, piece: boardState[m.fromRow][m.fromCol], score: evaluateMove(m, boardState, color) }));
        }

        function botMove() {
            if (gameOver || turn !== BLACK) return;
            const moves = getAllLegalMovesWithScore(board, BLACK);
            if (moves.length === 0) return;
            moves.sort((a, b) => b.score - a.score);
            const best = moves[0];
            const boardDiv = document.getElementById('chessboard');
            boardDiv.style.pointerEvents = 'none';
            executeMoveWithAnimation(best, (ended) => {
                if (!ended && turn === WHITE) {
                    selectedRow = null;
                    legalMoves = [];
                    renderBoard();
                    updateUI();
                }
                boardDiv.style.pointerEvents = 'auto';
            });
        }

        // ======================== رندر و تعامل کاربر ========================
        function renderBoard() {
            const container = document.getElementById('chessboard');
            container.innerHTML = '';
            for (let i = 0; i < 8; i++) {
                for (let j = 0; j < 8; j++) {
                    const piece = board[i][j];
                    const symbol = piece ? getPieceSymbol(piece) : '';
                    const div = document.createElement('div');
                    let className = `square ${(i + j) % 2 === 0 ? 'light' : 'dark'}`;
                    if (piece) {
                        className += ` ${piece.color === WHITE ? 'piece-white' : 'piece-black'}`;
                    }
                    div.className = className;
                    div.textContent = symbol;
                    if (selectedRow === i && selectedCol === j) div.classList.add('selected');
                    if (legalMoves.some(m => m.toRow === i && m.toCol === j)) div.classList.add('legal-move');
                    div.addEventListener('click', (function (r, c) { return () => onSquareClick(r, c); })(i, j));
                    container.appendChild(div);
                }
            }
        }

        function updateUI() {
            const turnTextElem = document.getElementById('turnText');
            const statusMsgElem = document.getElementById('statusMsg');
            if (gameOver) {
                if (winner === WHITE) turnTextElem.innerHTML = '🎉 شما برنده شدید!';
                else if (winner === BLACK) turnTextElem.innerHTML = '🤖 ربات پیروز شد';
                else turnTextElem.innerHTML = '♾️ مساوی (پات)';
                statusMsgElem.innerHTML = gameOver ? (winner ? 'پایان بازی' : 'پات') : '';
                return;
            }
            if (turn === WHITE) {
                turnTextElem.innerHTML = 'نوبت شما (سفید)';
                statusMsgElem.innerHTML = 'یک مهره را انتخاب کنید';
            } else {
                turnTextElem.innerHTML = 'نوبت ربات (سیاه)';
                statusMsgElem.innerHTML = 'ربات در حال حرکت...';
            }
            const kp = findKingPosition(turn);
            if (kp && isSquareAttacked(board, kp.row, kp.col, turn)) statusMsgElem.innerHTML += ' ⚠️ کیش!';
        }

        function onSquareClick(row, col) {
            if (gameOver || turn !== WHITE) return;
            if (selectedRow !== null) {
                const move = legalMoves.find(m => m.toRow === row && m.toCol === col);
                if (move) {
                    executeMoveWithAnimation(move, (ended) => {
                        selectedRow = null;
                        legalMoves = [];
                        renderBoard();
                        updateUI();
                        if (!ended && turn === BLACK) {
                            setTimeout(() => botMove(), 100);
                        }
                    });
                    return;
                } else {
                    selectedRow = null;
                    legalMoves = [];
                    renderBoard();
                    updateUI();
                }
            }
            const piece = board[row][col];
            if (piece && piece.color === WHITE) {
                selectedRow = row;
                selectedCol = col;
                const all = getAllLegalMovesForColor(board, WHITE);
                legalMoves = all.filter(m => m.fromRow === row && m.fromCol === col);
                renderBoard();
                updateUI();
            } else {
                selectedRow = null;
                legalMoves = [];
                renderBoard();
                updateUI();
            }
        }

        function resetGame() {
            initBoard();
            kingPos = { white: { row: 7, col: 4 }, black: { row: 0, col: 4 } };
            turn = WHITE;
            gameOver = false;
            winner = null;
            selectedRow = null;
            legalMoves = [];
            renderBoard();
            updateUI();
        }

        function generateStars() {
            const starContainer = document.getElementById('starBg');
            if (!starContainer) return;
            starContainer.innerHTML = '';
            for (let i = 0; i < 180; i++) {
                const star = document.createElement('div');
                star.classList.add('star');
                const size = 1 + Math.random() * 2.5;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.left = Math.random() * 100 + '%';
                star.style.top = Math.random() * 100 + '%';
                star.style.animationDuration = (2 + Math.random() * 4) + 's';
                star.style.animationDelay = Math.random() * 5 + 's';
                star.style.background = Math.random() > 0.7 ? '#ffdd88' : '#88ccff';
                star.style.boxShadow = '0 0 3px rgba(255,255,200,0.6)';
                starContainer.appendChild(star);
            }
        }

        initBoard();
        renderBoard();
        updateUI();
        generateStars();
        document.getElementById('resetBtn').addEventListener('click', resetGame);
    </script>
</body>

</html>