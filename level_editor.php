<?php
session_start();
include "db.php";
include "classroom_level_helpers.php";

function teacher_levels_has_column(mysqli $conn, string $column): bool
{
    $safe_column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM teacher_levels LIKE '$safe_column'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    echo "Access denied";
    exit();
}

$teacher_id = (int)$_SESSION['id'];
$message = '';
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$supports_spikes = teacher_levels_has_column($conn, 'spikes');
$supports_entities = teacher_levels_has_column($conn, 'entities');

$classroom_select_options = [];
$classroom_options_result = $conn->query("
SELECT id, name
FROM classrooms
WHERE teacher_id = $teacher_id
ORDER BY name ASC
");

if ($classroom_options_result && $classroom_options_result->num_rows > 0) {
    while ($classroom_option = $classroom_options_result->fetch_assoc()) {
        $classroom_select_options[] = $classroom_option;
    }
}

$has_classrooms = count($classroom_select_options) > 0;

$editing = [
    'id' => 0,
    'classroom_id' => (int)($_GET['classroom_id'] ?? 0),
    'title' => '',
    'description' => '',
    'instructions' => '',
    'difficulty' => 'beginner',
    'status' => 'draft',
    'grid_width' => 6,
    'grid_height' => 6,
    'start_x' => 0,
    'start_y' => 0,
    'goal_x' => 5,
    'goal_y' => 5,
    'walls' => '',
    'spikes' => '',
    'entities' => '[]',
];

$edit_id = (int)($_GET['id'] ?? 0);
if ($edit_id > 0) {
    $edit_result = $conn->query("SELECT * FROM teacher_levels WHERE id = $edit_id AND teacher_id = $teacher_id LIMIT 1");
    if ($edit_result && $edit_result->num_rows > 0) {
        $editing = $edit_result->fetch_assoc();
    } else {
        $message = 'That level could not be found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $level_id = (int)($_POST['level_id'] ?? 0);
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'beginner';
    $status = $_POST['status'] ?? 'draft';
    $grid_width = min(14, max(3, (int)($_POST['grid_width'] ?? 6)));
    $grid_height = min(14, max(3, (int)($_POST['grid_height'] ?? 6)));
    $start_x = max(0, (int)($_POST['start_x'] ?? 0));
    $start_y = max(0, (int)($_POST['start_y'] ?? 0));
    $goal_x = max(0, (int)($_POST['goal_x'] ?? 5));
    $goal_y = max(0, (int)($_POST['goal_y'] ?? 5));
    $walls = trim($_POST['walls'] ?? '');
    $spikes = trim($_POST['spikes'] ?? '');
    $entities = trim($_POST['entities'] ?? '[]');

    $existing_level = null;
    if ($level_id > 0) {
        $existing_level_result = $conn->query("
            SELECT id, classroom_id, title
            FROM teacher_levels
            WHERE id = $level_id
              AND teacher_id = $teacher_id
            LIMIT 1
        ");

        if ($existing_level_result && $existing_level_result->num_rows > 0) {
            $existing_level = $existing_level_result->fetch_assoc();
        }
    }

    if ($existing_level && (int)$existing_level['classroom_id'] === $classroom_id) {
        $title = trim((string)$existing_level['title']) !== ''
            ? (string)$existing_level['title']
            : next_classroom_level_title($conn, $classroom_id, $level_id);
    } else {
        $title = $classroom_id > 0 ? next_classroom_level_title($conn, $classroom_id, $level_id) : 'Level';
    }

    $editing = [
        'id' => $level_id,
        'classroom_id' => $classroom_id,
        'title' => $title,
        'description' => $description,
        'instructions' => $instructions,
        'difficulty' => $difficulty,
        'status' => $status,
        'grid_width' => $grid_width,
        'grid_height' => $grid_height,
        'start_x' => $start_x,
        'start_y' => $start_y,
        'goal_x' => $goal_x,
        'goal_y' => $goal_y,
        'walls' => $walls,
        'spikes' => $spikes,
        'entities' => $entities,
    ];

    $classroom_check = $conn->query("SELECT id FROM classrooms WHERE id = $classroom_id AND teacher_id = $teacher_id LIMIT 1");

    $coordinates_valid =
        $start_x < $grid_width &&
        $goal_x < $grid_width &&
        $start_y < $grid_height &&
        $goal_y < $grid_height &&
        !($start_x === $goal_x && $start_y === $goal_y);

    if ($description !== '' && $instructions !== '' && $classroom_check && $classroom_check->num_rows > 0 && $coordinates_valid) {
        $save_ok = false;

        if ($level_id > 0) {
            if ($supports_spikes && $supports_entities) {
                $stmt = $conn->prepare("
                    UPDATE teacher_levels
                    SET classroom_id = ?,
                        title = ?,
                        description = ?,
                        instructions = ?,
                        difficulty = ?,
                        status = ?,
                        grid_width = ?,
                        grid_height = ?,
                        start_x = ?,
                        start_y = ?,
                        goal_x = ?,
                        goal_y = ?,
                        walls = ?,
                        spikes = ?,
                        entities = ?
                    WHERE id = ?
                      AND teacher_id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        'isssssiiiiiisssii',
                        $classroom_id,
                        $title,
                        $description,
                        $instructions,
                        $difficulty,
                        $status,
                        $grid_width,
                        $grid_height,
                        $start_x,
                        $start_y,
                        $goal_x,
                        $goal_y,
                        $walls,
                        $spikes,
                        $entities,
                        $level_id,
                        $teacher_id
                    );
                    $save_ok = $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("
                    UPDATE teacher_levels
                    SET classroom_id = ?,
                        title = ?,
                        description = ?,
                        instructions = ?,
                        difficulty = ?,
                        status = ?,
                        grid_width = ?,
                        grid_height = ?,
                        start_x = ?,
                        start_y = ?,
                        goal_x = ?,
                        goal_y = ?,
                        walls = ?
                    WHERE id = ?
                      AND teacher_id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        'isssssiiiiiisii',
                        $classroom_id,
                        $title,
                        $description,
                        $instructions,
                        $difficulty,
                        $status,
                        $grid_width,
                        $grid_height,
                        $start_x,
                        $start_y,
                        $goal_x,
                        $goal_y,
                        $walls,
                        $level_id,
                        $teacher_id
                    );
                    $save_ok = $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            if ($supports_spikes && $supports_entities) {
                $stmt = $conn->prepare("
                    INSERT INTO teacher_levels (
                        teacher_id, classroom_id, title, description, instructions, difficulty, status,
                        grid_width, grid_height, start_x, start_y, goal_x, goal_y, walls, spikes, entities
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        'iisssssiiiiiisss',
                        $teacher_id,
                        $classroom_id,
                        $title,
                        $description,
                        $instructions,
                        $difficulty,
                        $status,
                        $grid_width,
                        $grid_height,
                        $start_x,
                        $start_y,
                        $goal_x,
                        $goal_y,
                        $walls,
                        $spikes,
                        $entities
                    );
                    $save_ok = $stmt->execute();
                    if ($save_ok) {
                        $level_id = (int)$stmt->insert_id;
                    }
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO teacher_levels (
                        teacher_id, classroom_id, title, description, instructions, difficulty, status,
                        grid_width, grid_height, start_x, start_y, goal_x, goal_y, walls
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param(
                        'iisssssiiiiiis',
                        $teacher_id,
                        $classroom_id,
                        $title,
                        $description,
                        $instructions,
                        $difficulty,
                        $status,
                        $grid_width,
                        $grid_height,
                        $start_x,
                        $start_y,
                        $goal_x,
                        $goal_y,
                        $walls
                    );
                    $save_ok = $stmt->execute();
                    if ($save_ok) {
                        $level_id = (int)$stmt->insert_id;
                    }
                    $stmt->close();
                }
            }
        }

        if ($save_ok && $level_id > 0) {
            header('Location: level_editor.php?id=' . $level_id . '&saved=1');
            exit();
        }

        $message = 'The level could not be saved. Check that the selected classroom exists and that the database schema is up to date.';
        if ((!$supports_spikes || !$supports_entities) && $message !== '') {
            $message .= ' Extra gameplay pieces will not persist until `teacher_levels.spikes` and `teacher_levels.entities` are added.';
        }
    }
    elseif ($message === '') {
        $message = 'Complete all required fields and keep the start and goal in valid, separate cells.';
    }
}

$editor_walls = trim((string)($editing['walls'] ?? ''));
$editor_spikes = trim((string)($editing['spikes'] ?? ''));
$editor_entities = trim((string)($editing['entities'] ?? '[]'));
$editor_wall_points = [];
$editor_spike_points = [];
$editor_entity_points = [];
if ($editor_walls !== '') {
    $editor_parts = preg_split('/\s+/', $editor_walls);
    foreach ($editor_parts as $editor_part) {
        $editor_coords = explode(',', $editor_part);
        if (count($editor_coords) === 2) {
            $editor_wall_points[] = [
                'x' => (int)$editor_coords[0],
                'y' => (int)$editor_coords[1],
            ];
        }
    }
}

if ($editor_spikes !== '') {
    $editor_parts = preg_split('/\s+/', $editor_spikes);
    foreach ($editor_parts as $editor_part) {
        $editor_coords = explode(',', $editor_part);
        if (count($editor_coords) === 2) {
            $editor_spike_points[] = [
                'x' => (int)$editor_coords[0],
                'y' => (int)$editor_coords[1],
            ];
        }
    }
}

$decoded_editor_entities = json_decode($editor_entities, true);
if (is_array($decoded_editor_entities)) {
    foreach ($decoded_editor_entities as $editor_entity) {
        if (!is_array($editor_entity)) {
            continue;
        }
        $editor_entity_points[] = [
            'type' => (string)($editor_entity['type'] ?? ''),
            'x' => (int)($editor_entity['x'] ?? 0),
            'y' => (int)($editor_entity['y'] ?? 0),
        ];
    }
}

$classroom_level_previews = [];
foreach ($classroom_select_options as $classroom_option) {
    $preview_classroom_id = (int)$classroom_option['id'];
    if ((int)$editing['id'] > 0 && (int)$editing['classroom_id'] === $preview_classroom_id && trim((string)$editing['title']) !== '') {
        $classroom_level_previews[$preview_classroom_id] = (string)$editing['title'];
        continue;
    }

    $classroom_level_previews[$preview_classroom_id] = next_classroom_level_title($conn, $preview_classroom_id, (int)$editing['id']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Level Editor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="tab">
        <a href="index.php">
            <img src="img\logo.png" class="logo">
        </a>
        <div class="nav-buttons">
            <div class="profile" onclick="toggleMenu()">
                <img src="img\default-pfp.png" class="profile-img">
                <div id="dropdown" class="dropdown">
                    <a href="profile.php">Profile</a>
                    <a href="teacher_levels.php">Teacher Dashboard</a>
                    <a href="level_editor.php">Level Editor</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="content teacher_content">
        <div class="page teacher_page editor_page">
            <div class="report_header">
                <div>
                    <h2><?= (int)$editing['id'] > 0 ? 'Edit Classroom Level' : 'Create Classroom Level' ?></h2>
                    <p>Build the actual classroom stage in the browser, place gameplay pieces, and published students will open it in the Godot game.</p>
                </div>
                <div class="table_actions">
                    <a class="secondary_button" href="teacher_levels.php">Back to Dashboard</a>
                </div>
            </div>

            <?php if ($saved): ?>
                <div class="callout">
                    <strong>Level saved.</strong>
                    <span>Published levels now appear on the classroom levels screen and open through the Godot gameplay with your placed pieces.</span>
                </div>
            <?php endif; ?>

            <?php if ($message !== ''): ?>
                <div class="form_error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!$has_classrooms): ?>
                <div class="empty_state">
                    <strong>Create a classroom first.</strong>
                    <span>The editor is only available after at least one classroom exists.</span>
                </div>
            <?php else: ?>
                <form method="POST" class="editor_shell">
                    <input type="hidden" name="level_id" value="<?= (int)$editing['id'] ?>">

                    <div class="editor_sidebar">
                        <div class="teacher_form">
                            <div class="form_heading">
                                <h3>Level Details</h3>
                            </div>

                            <label for="classroom_id">Classroom</label>
                            <select id="classroom_id" name="classroom_id" required>
                                <option value="">Select classroom</option>
                                <?php foreach ($classroom_select_options as $classroom): ?>
                                    <option value="<?= (int)$classroom['id'] ?>" <?= ((int)$editing['classroom_id'] === (int)$classroom['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($classroom['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="callout compact_notice">
                                <strong>Assigned level number</strong>
                                <span id="level-title-preview"><?= htmlspecialchars($editing['title'] !== '' ? $editing['title'] : ($classroom_level_previews[(int)$editing['classroom_id']] ?? 'Level')) ?></span>
                            </div>

                            <label for="description">Short description</label>
                            <input type="text" id="description" name="description" required value="<?= htmlspecialchars($editing['description']) ?>">

                            <label for="instructions">Instructions</label>
                            <textarea id="instructions" name="instructions" rows="6" required><?= htmlspecialchars($editing['instructions']) ?></textarea>

                            <div class="teacher_form_row">
                                <div>
                                    <label for="difficulty">Difficulty</label>
                                    <select id="difficulty" name="difficulty">
                                        <option value="beginner" <?= $editing['difficulty'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                                        <option value="intermediate" <?= $editing['difficulty'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                        <option value="advanced" <?= $editing['difficulty'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="status">Status</label>
                                    <select id="status" name="status">
                                        <option value="draft" <?= $editing['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                        <option value="published" <?= $editing['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                                    </select>
                                </div>
                            </div>

                            <div class="teacher_form_row">
                                <div>
                                    <label for="grid_width">Grid width</label>
                                    <input type="number" id="grid_width" name="grid_width" min="3" max="14" value="<?= (int)$editing['grid_width'] ?>">
                                </div>
                                <div>
                                    <label for="grid_height">Grid height</label>
                                    <input type="number" id="grid_height" name="grid_height" min="3" max="14" value="<?= (int)$editing['grid_height'] ?>">
                                </div>
                            </div>

                            <div class="teacher_form_row">
                                <div>
                                    <label for="start_x">Start X</label>
                                    <input type="number" id="start_x" name="start_x" min="0" value="<?= (int)$editing['start_x'] ?>">
                                </div>
                                <div>
                                    <label for="start_y">Start Y</label>
                                    <input type="number" id="start_y" name="start_y" min="0" value="<?= (int)$editing['start_y'] ?>">
                                </div>
                                <div>
                                    <label for="goal_x">Goal X</label>
                                    <input type="number" id="goal_x" name="goal_x" min="0" value="<?= (int)$editing['goal_x'] ?>">
                                </div>
                                <div>
                                    <label for="goal_y">Goal Y</label>
                                    <input type="number" id="goal_y" name="goal_y" min="0" value="<?= (int)$editing['goal_y'] ?>">
                                </div>
                            </div>

                            <input type="hidden" id="walls" name="walls" value="<?= htmlspecialchars($editing['walls']) ?>">
                            <input type="hidden" id="spikes" name="spikes" value="<?= htmlspecialchars($editing['spikes'] ?? '') ?>">
                            <input type="hidden" id="entities" name="entities" value="<?= htmlspecialchars($editing['entities'] ?? '[]', ENT_QUOTES) ?>">

                            <div class="callout compact_notice">
                                <strong>Level piece summary</strong>
                                <span id="editor-summary">0 walls, 0 spikes, 0 placed entities</span>
                            </div>

                            <button type="submit"><?= (int)$editing['id'] > 0 ? 'Save Changes' : 'Create Level' ?></button>
                        </div>
                    </div>

                    <div class="editor_workspace">
                        <div class="level_editor_panel">
                            <div class="form_heading">
                                <h3>Visual Layout</h3>
                                <span class="badge">Web editor</span>
                            </div>
                            <div class="editor_toolbar">
                                <button type="button" class="editor_tool is-active" data-tool="wall">Wall</button>
                                <button type="button" class="editor_tool" data-tool="spike">Spike</button>
                                <button type="button" class="editor_tool" data-tool="start">Start</button>
                                <button type="button" class="editor_tool" data-tool="goal">Goal</button>
                                <button type="button" class="editor_tool" data-tool="key">Key</button>
                                <button type="button" class="editor_tool" data-tool="door">Door</button>
                                <button type="button" class="editor_tool" data-tool="locked_door">Locked Door</button>
                                <button type="button" class="editor_tool" data-tool="stun_gun">Stun Gun</button>
                                <button type="button" class="editor_tool" data-tool="enemy">Enemy</button>
                                <button type="button" class="editor_tool" data-tool="erase">Erase</button>
                            </div>
                            <div class="editor_legend">
                                <span><strong>C</strong> Start</span>
                                <span><strong>G</strong> Goal</span>
                                <span><strong>S</strong> Spike</span>
                                <span><strong>K</strong> Key</span>
                                <span><strong>D</strong> Door</span>
                                <span><strong>L</strong> Locked door</span>
                                <span><strong>T</strong> Stun gun</span>
                                <span><strong>E</strong> Enemy</span>
                                <span>Drag to paint walls or spikes</span>
                            </div>
                            <div
                                id="teacher-level-editor"
                                class="level_board editor_board editor_board_large"
                                data-width="<?= (int)$editing['grid_width'] ?>"
                                data-height="<?= (int)$editing['grid_height'] ?>"
                                data-start-x="<?= (int)$editing['start_x'] ?>"
                                data-start-y="<?= (int)$editing['start_y'] ?>"
                                data-goal-x="<?= (int)$editing['goal_x'] ?>"
                                data-goal-y="<?= (int)$editing['goal_y'] ?>"
                                data-walls='<?= htmlspecialchars(json_encode($editor_wall_points), ENT_QUOTES) ?>'
                                data-spikes='<?= htmlspecialchars(json_encode($editor_spike_points), ENT_QUOTES) ?>'
                                data-entities='<?= htmlspecialchars(json_encode($editor_entity_points), ENT_QUOTES) ?>'
                            ></div>
                        </div>

                        <div class="callout">
                            <strong>Classroom publishing flow</strong>
                            <span>Save the stage here, set the level to <code>published</code>, and students in that classroom will see it on the classroom levels page and open the exact classroom stage in the Godot game.</span>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleMenu() {
        var menu = document.getElementById("dropdown");
        menu.style.display = (menu.style.display === "flex") ? "none" : "flex";
    }

    (function () {
        const editor = document.getElementById('teacher-level-editor');
        if (!editor) return;

        const gridWidthInput = document.getElementById('grid_width');
        const gridHeightInput = document.getElementById('grid_height');
        const classroomInput = document.getElementById('classroom_id');
        const titlePreview = document.getElementById('level-title-preview');
        const startXInput = document.getElementById('start_x');
        const startYInput = document.getElementById('start_y');
        const goalXInput = document.getElementById('goal_x');
        const goalYInput = document.getElementById('goal_y');
        const wallsInput = document.getElementById('walls');
        const spikesInput = document.getElementById('spikes');
        const entitiesInput = document.getElementById('entities');
        const summary = document.getElementById('editor-summary');
        const toolButtons = document.querySelectorAll('.editor_tool');

        let activeTool = 'wall';
        let gridWidth = parseInt(editor.dataset.width, 10);
        let gridHeight = parseInt(editor.dataset.height, 10);
        let start = { x: parseInt(editor.dataset.startX, 10), y: parseInt(editor.dataset.startY, 10) };
        let goal = { x: parseInt(editor.dataset.goalX, 10), y: parseInt(editor.dataset.goalY, 10) };
        let walls = new Set((JSON.parse(editor.dataset.walls || '[]')).map((wall) => wall.x + ',' + wall.y));
        let spikes = new Set((JSON.parse(editor.dataset.spikes || '[]')).map((spike) => spike.x + ',' + spike.y));
        let entities = new Map((JSON.parse(editor.dataset.entities || '[]')).map((entity) => [entity.x + ',' + entity.y, entity.type]));
        let isPointerDown = false;

        const entityLabels = {
            key: 'K',
            door: 'D',
            locked_door: 'L',
            stun_gun: 'T',
            enemy: 'E'
        };
        const levelTitlePreviews = <?= json_encode($classroom_level_previews) ?>;

        function setActiveTool(tool) {
            activeTool = tool;
            toolButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.tool === tool);
            });
        }

        function syncFields() {
            startXInput.value = start.x;
            startYInput.value = start.y;
            goalXInput.value = goal.x;
            goalYInput.value = goal.y;
            wallsInput.value = Array.from(walls).sort().join(' ');
            spikesInput.value = Array.from(spikes).sort().join(' ');
            entitiesInput.value = JSON.stringify(Array.from(entities.entries()).map(function (entry) {
                const coords = entry[0].split(',').map(Number);
                return {
                    type: entry[1],
                    x: coords[0],
                    y: coords[1]
                };
            }));
            summary.textContent = walls.size + ' walls, ' + spikes.size + ' spikes, ' + entities.size + ' placed entities';
        }

        function clearCellContent(targetKey) {
            walls.delete(targetKey);
            spikes.delete(targetKey);
            entities.delete(targetKey);
        }

        function clampPoint(point) {
            point.x = Math.max(0, Math.min(gridWidth - 1, point.x));
            point.y = Math.max(0, Math.min(gridHeight - 1, point.y));
        }

        function normalizeState() {
            gridWidth = Math.max(3, parseInt(gridWidthInput.value || '6', 10));
            gridHeight = Math.max(3, parseInt(gridHeightInput.value || '6', 10));
            gridWidthInput.value = gridWidth;
            gridHeightInput.value = gridHeight;

            start = {
                x: parseInt(startXInput.value || '0', 10),
                y: parseInt(startYInput.value || '0', 10)
            };
            goal = {
                x: parseInt(goalXInput.value || '0', 10),
                y: parseInt(goalYInput.value || '0', 10)
            };

            clampPoint(start);
            clampPoint(goal);

            if (start.x === goal.x && start.y === goal.y) {
                goal.x = Math.min(gridWidth - 1, start.x + 1);
                goal.y = start.y;
                if (goal.x === start.x && goal.y === start.y) {
                    goal.y = Math.min(gridHeight - 1, start.y + 1);
                }
            }

            walls = new Set(Array.from(walls).filter((key) => {
                const [x, y] = key.split(',').map(Number);
                if (x < 0 || y < 0 || x >= gridWidth || y >= gridHeight) return false;
                if (x === start.x && y === start.y) return false;
                if (x === goal.x && y === goal.y) return false;
                if (entities.has(key)) return false;
                return true;
            }));

            spikes = new Set(Array.from(spikes).filter((key) => {
                const [x, y] = key.split(',').map(Number);
                if (x < 0 || y < 0 || x >= gridWidth || y >= gridHeight) return false;
                if (x === start.x && y === start.y) return false;
                if (x === goal.x && y === goal.y) return false;
                if (entities.has(key)) return false;
                return true;
            }));

            entities = new Map(Array.from(entities.entries()).filter((entry) => {
                const coords = entry[0].split(',').map(Number);
                const x = coords[0];
                const y = coords[1];
                if (x < 0 || y < 0 || x >= gridWidth || y >= gridHeight) return false;
                if (x === start.x && y === start.y) return false;
                if (x === goal.x && y === goal.y) return false;
                if (walls.has(entry[0]) || spikes.has(entry[0])) return false;
                return true;
            }));

            syncFields();
        }

        function applyToolAt(x, y) {
            const targetKey = x + ',' + y;

            if (activeTool === 'wall') {
                clearCellContent(targetKey);
                if (targetKey !== start.x + ',' + start.y && targetKey !== goal.x + ',' + goal.y) walls.add(targetKey);
            }

            if (activeTool === 'spike') {
                clearCellContent(targetKey);
                if (targetKey !== start.x + ',' + start.y && targetKey !== goal.x + ',' + goal.y) spikes.add(targetKey);
            }

            if (activeTool === 'erase') {
                walls.delete(targetKey);
                spikes.delete(targetKey);
                entities.delete(targetKey);
            }

            if (activeTool === 'start') {
                start = { x: x, y: y };
                clearCellContent(targetKey);
                if (goal.x === x && goal.y === y) {
                    goal = { x: Math.min(gridWidth - 1, x + 1), y: y };
                }
            }

            if (activeTool === 'goal') {
                goal = { x: x, y: y };
                clearCellContent(targetKey);
                if (start.x === x && start.y === y) {
                    start = { x: Math.max(0, x - 1), y: y };
                }
            }

            if (['key', 'door', 'locked_door', 'stun_gun', 'enemy'].includes(activeTool)) {
                clearCellContent(targetKey);
                if (targetKey !== start.x + ',' + start.y && targetKey !== goal.x + ',' + goal.y) {
                    entities.set(targetKey, activeTool);
                }
            }
        }

        function renderEditor() {
            normalizeState();
            editor.style.gridTemplateColumns = 'repeat(' + gridWidth + ', minmax(36px, 1fr))';
            editor.innerHTML = '';

            for (let y = 0; y < gridHeight; y++) {
                for (let x = 0; x < gridWidth; x++) {
                    const cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'board_cell editor_cell editor_cell_large';

                    const key = x + ',' + y;
                    if (walls.has(key)) {
                        cell.classList.add('wall');
                    }

                    if (spikes.has(key)) {
                        cell.classList.add('spike');
                        cell.textContent = 'S';
                    }

                    if (goal.x === x && goal.y === y) {
                        cell.classList.add('goal');
                        cell.textContent = 'G';
                    }

                    if (start.x === x && start.y === y) {
                        cell.classList.add('player');
                        cell.textContent = 'C';
                    }

                    if (entities.has(key)) {
                        cell.classList.add('entity-piece');
                        cell.classList.add('entity-' + entities.get(key));
                        cell.textContent = entityLabels[entities.get(key)] || '?';
                    }

                    cell.addEventListener('pointerdown', function (event) {
                        event.preventDefault();
                        isPointerDown = true;
                        applyToolAt(x, y);
                        renderEditor();
                    });

                    cell.addEventListener('pointerenter', function () {
                        if (!isPointerDown) return;
                        if (activeTool !== 'wall' && activeTool !== 'spike' && activeTool !== 'erase') return;
                        applyToolAt(x, y);
                        renderEditor();
                    });

                    editor.appendChild(cell);
                }
            }
        }

        toolButtons.forEach((button) => {
            button.addEventListener('click', function () {
                setActiveTool(button.dataset.tool);
            });
        });

        if (classroomInput && titlePreview) {
            classroomInput.addEventListener('change', function () {
                titlePreview.textContent = levelTitlePreviews[classroomInput.value] || 'Level';
            });
        }

        [gridWidthInput, gridHeightInput, startXInput, startYInput, goalXInput, goalYInput].forEach((input) => {
            input.addEventListener('input', renderEditor);
        });

        wallsInput.addEventListener('input', function () {
            const nextWalls = new Set();
            wallsInput.value.trim().split(/\s+/).filter(Boolean).forEach((part) => {
                const coords = part.split(',');
                if (coords.length === 2) {
                    nextWalls.add(parseInt(coords[0], 10) + ',' + parseInt(coords[1], 10));
                }
            });
            walls = nextWalls;
            renderEditor();
        });

        window.addEventListener('pointerup', function () {
            isPointerDown = false;
        });

        setActiveTool('wall');
        renderEditor();
    }());
    </script>
</body>
</html>
