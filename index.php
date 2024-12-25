<?php
session_start();

// Include the database configuration
require_once __DIR__ . '/config/database.php';

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: public/login.php');
    exit;
}

// Check user role
$is_admin = ($_SESSION['role'] === 'admin');

// Fetch all products
try {
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $quantity = (int)$_POST['quantity'];
    $price_no_tax = (float)$_POST['price_no_tax'];
    $price_with_tax = (float)$_POST['price_with_tax'];
    $price_by_dozen = (float)$_POST['price_by_dozen'];

    // Insert product into the database
    try {
        $stmt = $pdo->prepare("INSERT INTO products (code, name, description, quantity, price_no_tax, price_with_tax, price_by_dozen) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $description, $quantity, $price_no_tax, $price_with_tax, $price_by_dozen]);
        header('Location: index.php'); // Refresh the page after adding the product
        exit;
    } catch (Exception $e) {
        $error = 'Error adding product: ' . $e->getMessage();
    }
}

// Handle product discount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discount_product'])) {
    $product_id = (int)$_POST['product_id'];
    $discount_quantity = (int)$_POST['discount_quantity'];

    // Update product quantity
    try {
        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
        $stmt->execute([$discount_quantity, $product_id, $discount_quantity]);
        if ($stmt->rowCount() === 0) {
            $error = 'Error: Cantidad insuficiente o producto invalido.';
        } else {
            header('Location: index.php'); // Refresh the page after the update
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error updating product: ' . $e->getMessage();
    }
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addition_product'])) {
    $product_id = (int)$_POST['product_id'];
    $addition_quantity = (int)$_POST['addition_quantity'];

    // Update product quantity
    try {
        // Remove the quantity >= ? condition
        $stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
        $stmt->execute([$addition_quantity, $product_id]);
        if ($stmt->rowCount() === 0) {
            $error = 'Error: Producto no válido.';
        } else {
            header('Location: index.php'); // Refresh the page after the update
            exit;
        }
    } catch (Exception $e) {
        $error = 'Error updating product: ' . $e->getMessage();
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];
    $code = $_POST['code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $quantity = (int)$_POST['quantity'];
    $price_no_tax = (float)$_POST['price_no_tax'];
    $price_with_tax = (float)$_POST['price_with_tax'];
    $price_by_dozen = (float)$_POST['price_by_dozen'];

    try {
        $stmt = $pdo->prepare("UPDATE products SET code = ?, name = ?, description = ?, quantity = ?, price_no_tax = ?, price_with_tax = ?, price_by_dozen = ? WHERE id = ?");
        $stmt->execute([$code, $name, $description, $quantity, $price_no_tax, $price_with_tax, $price_by_dozen, $product_id]);
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = 'Error updating product: ' . $e->getMessage();
    }
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = (int)$_POST['product_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = 'Error deleting product: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manejo de Inventario</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="app/styles/app.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand">
                <img src="" alt="Logo" style="height: 50px;"> Sistema de Inventario
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Bienvenido, <?= htmlspecialchars($_SESSION['username']); ?> (<?= htmlspecialchars($_SESSION['role']); ?>)</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="public/logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5">
        <h3 class="text-center mb-4">Inventario Bodega</h3>

        <!-- Admin Actions -->
        <div class="search-bar-container mb-4">
            <button class="btn btn-success add-product-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">Agregar Producto</button>
            <button class="btn btn-primary addition-product-btn" data-bs-toggle="modal" data-bs-target="#additionProductModal">Sumar Producto</button>
            <button class="btn btn-warning discount-product-btn" data-bs-toggle="modal" data-bs-target="#discountProductModal">Descontar Producto</button>
            <div class="search-bar">
                <input type="text" id="searchBar" class="form-control" placeholder="Buscar producto...">
            </div>
        </div>

        <!-- Product Table -->
        <div class="table-container table-responsive">
            <table class="table table-striped" id="productTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <?php if ($is_admin): ?>
                        <th>Costo</th>
                        <?php endif; ?>
                        <th>Precio final</th>
                        <th>Precio por docena</th>
                        <?php if ($is_admin): ?>
                            <th>Opciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id']); ?></td>
                                <td class="code"><?= htmlspecialchars($product['code']); ?></td>
                                <td class="name"><?= htmlspecialchars($product['name']); ?></td>
                                <td class="description"><?= htmlspecialchars($product['description']); ?></td>
                                <td><?= htmlspecialchars($product['quantity']); ?></td>
                                <?php if ($is_admin): ?>
                                <td>$<?= number_format($product['price_no_tax'], 2); ?></td>
                                <?php endif; ?>
                                <td>$<?= number_format($product['price_with_tax'], 2); ?></td>
                                <td>$<?= number_format($product['price_by_dozen'], 2); ?></td>
                                <?php if ($is_admin): ?>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-warning btn-sm btn-edit" data-product='<?= json_encode($product); ?>'>Editar</button>
                                            <button class="btn btn-danger btn-sm btn-delete" 
                                                data-product-id="<?= $product['id']; ?>" 
                                                data-product-name="<?= htmlspecialchars($product['name']); ?>" 
                                                data-product-description="<?= htmlspecialchars($product['description']); ?>">Eliminar</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_admin ? 9 : 8; ?>" class="text-center">No se encontraron prodcutos</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProductModalLabel">Agregar Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="code" class="form-label">Código</label>
                            <input type="text" name="code" id="code" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Producto</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Cantidad</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="price_no_tax" class="form-label">Costo</label>
                            <input type="number" name="price_no_tax" id="price_no_tax" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="price_with_tax" class="form-label">Precio</label>
                            <input type="number" name="price_with_tax" id="price_with_tax" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="price_by_dozen" class="form-label">Precio por docena</label>
                            <input type="number" name="price_by_dozen" id="price_by_dozen" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Agregar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Product Modal -->
    <div class="modal fade" id="updateProductModal" tabindex="-1" aria-labelledby="updateProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="updateProductForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="updateProductModalLabel">Editar Prodcuto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="updateProductId">
                        <div class="mb-3">
                            <label for="updateCode" class="form-label">Código</label>
                            <input type="text" name="code" id="updateCode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="updateName" class="form-label">Producto</label>
                            <input type="text" name="name" id="updateName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="updateDescription" class="form-label">Descripción</label>
                            <textarea name="description" id="updateDescription" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="updateQuantity" class="form-label">Cantidad</label>
                            <input type="number" name="quantity" id="updateQuantity" class="form-control" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="updatePriceNoTax" class="form-label">Costo</label>
                            <input type="number" name="price_no_tax" id="updatePriceNoTax" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="updatePriceWithTax" class="form-label">Precio</label>
                            <input type="number" name="price_with_tax" id="updatePriceWithTax" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="updatePriceByDozen" class="form-label">Precio por docena</label>
                            <input type="number" name="price_by_dozen" id="updatePriceByDozen" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" name="update_product" class="btn btn-primary">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Product Confirmation Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteProductForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteProductModalLabel">Eliminar Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Esta seguro que desea eliminar este producto?</p>
                        <p><strong>Producto:</strong> <span id="deleteProductName"></span></p>
                        <p><strong>Descripción:</strong> <span id="deleteProductDescription"></span></p>
                        <input type="hidden" name="product_id" id="deleteProductId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_product" class="btn btn-danger">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Discount Product Modal -->
    <div class="modal fade" id="discountProductModal" tabindex="-1" aria-labelledby="discountProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="discountProductModalLabel">Descontar Cantidad</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <input type="text" id="searchAdditionProductModal" class="form-control" placeholder="Busca un producto...">
                        </div>
                        <div class="mb-3">
                            <label for="product_id" class="form-label">Selecciona un producto</label>
                            <select name="product_id" id="product_id" class="form-select" required>
                                <option value="" disabled selected>Selecciona un producto...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>" class="product-option" data-code="<?= htmlspecialchars($product['code']); ?>">
                                        <?= htmlspecialchars($product['name']) . ' (Cantidad: ' . $product['quantity'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="discount_quantity" class="form-label">Cantidad a descontar</label>
                            <input type="number" name="discount_quantity" id="discount_quantity" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" name="discount_product" class="btn btn-primary">Descontar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Addition Product Modal -->
    <div class="modal fade" id="additionProductModal" tabindex="-1" aria-labelledby="additionProductModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="additionProductModalLabel">Sumar Cantidad</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <input type="text" id="searchProductModal" class="form-control" placeholder="Busca un producto...">
                        </div>
                        <div class="mb-3">
                            <label for="product_id" class="form-label">Selecciona un producto</label>
                            <select name="product_id" id="product_id" class="form-select" required>
                                <option value="" disabled selected>Selecciona un producto...</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>" class="product-option" data-code="<?= htmlspecialchars($product['code']); ?>">
                                        <?= htmlspecialchars($product['name']) . ' (Cantidad: ' . $product['quantity'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="discount_quantity" class="form-label">Cantidad a sumar</label>
                            <input type="number" name="addition_quantity" id="addition_quantity" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" name="addition_product" class="btn btn-primary">Agregar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        const searchBar = document.getElementById('searchBar');
    const tableRows = document.querySelectorAll('#productTable tbody tr');
    const tableBody = document.querySelector('#productTable tbody');

    searchBar.addEventListener('input', function () {
        const searchValue = searchBar.value.toLowerCase().trim();
        let hasResults = false;

        tableRows.forEach(row => {
            const name = row.querySelector('.name').textContent.toLowerCase();
            const code = row.querySelector('.code').textContent.toLowerCase();

            if (name.includes(searchValue) || code.includes(searchValue)) {
                row.classList.remove('hidden');
                hasResults = true;
            } else {
                row.classList.add('hidden');
            }
        });

        // Display "No products found" row if no matches
        const noResultsRow = document.getElementById('no-results');
        if (!hasResults) {
            if (!noResultsRow) {
                const newNoResultsRow = document.createElement('tr');
                newNoResultsRow.id = 'no-results';
                newNoResultsRow.innerHTML = `<td colspan="9" class="text-center">No products found</td>`;
                tableBody.appendChild(newNoResultsRow);
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    });

        const searchProductModal = document.getElementById('searchProductModal');
        const productOptions = document.querySelectorAll('#product_id .product-option');

        searchProductModal.addEventListener('input', function () {
            const searchValue = searchProductModal.value.toLowerCase().trim();
            productOptions.forEach(option => {
                const productName = option.textContent.toLowerCase();
                const productCode = option.getAttribute('data-code').toLowerCase();
                if (productName.includes(searchValue) || productCode.includes(searchValue)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        const searchAdditionProductModal = document.getElementById('searchAdditionProductModal');
        const productAdditionOptions = document.querySelectorAll('#product_id .product-option');

        searchAdditionProductModal.addEventListener('input', function () {
            const searchValue = searchAdditionProductModal.value.toLowerCase().trim();
            productAdditionOptions.forEach(option => {
                const productName = option.textContent.toLowerCase();
                const productCode = option.getAttribute('data-code').toLowerCase();
                if (productName.includes(searchValue) || productCode.includes(searchValue)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', () => {
                const productData = JSON.parse(button.getAttribute('data-product'));
                document.getElementById('updateProductId').value = productData.id;
                document.getElementById('updateCode').value = productData.code;
                document.getElementById('updateName').value = productData.name;
                document.getElementById('updateDescription').value = productData.description;
                document.getElementById('updateQuantity').value = productData.quantity;
                document.getElementById('updatePriceNoTax').value = productData.price_no_tax;
                document.getElementById('updatePriceWithTax').value = productData.price_with_tax;
                document.getElementById('updatePriceByDozen').value = productData.price_by_dozen;
                const updateModal = new bootstrap.Modal(document.getElementById('updateProductModal'));
                updateModal.show();
            });
        });

        const deleteButtons = document.querySelectorAll('.btn-delete');
        const deleteProductModal = document.getElementById('deleteProductModal');
        const deleteProductName = document.getElementById('deleteProductName');
        const deleteProductDescription = document.getElementById('deleteProductDescription');
        const deleteProductId = document.getElementById('deleteProductId');
        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                deleteProductName.textContent = button.getAttribute('data-product-name');
                deleteProductDescription.textContent = button.getAttribute('data-product-description');
                deleteProductId.value = button.getAttribute('data-product-id');
                const modal = new bootstrap.Modal(deleteProductModal);
                modal.show();
            });
        });
    </script>
</body>
</html>