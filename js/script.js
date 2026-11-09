
// 1. PRODUCTS DATA
let products = [];

// 2. CART
let cart = JSON.parse(localStorage.getItem("webstart-cart")) || [];

// 3. SELECT ELEMENTS
const productsGrid = document.getElementById("productsGrid");
const cartCount = document.getElementById("cartCount");
const cartItems = document.getElementById("cartItems");
const cartTotal = document.getElementById("cartTotal");
const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");

// LOAD PRODUCTS FROM PHP API
async function loadProducts() {
    try {
        const response = await fetch("api/products.php");

        if (!response.ok) {
            throw new Error("Erreur HTTP: " + response.status);
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.products)) {
            throw new Error("Format de données invalide");
        }

        products = data.products;

        // Charger les catégories depuis MySQL
        categoryFilter.innerHTML =
            '<option value="all">Toutes les catégories</option>';

        const categories = [...new Set(
            products.map(product => product.category)
        )];

        categories.forEach(category => {
            const option = document.createElement("option");
            option.value = category;
            option.textContent = category;
            categoryFilter.appendChild(option);
        });

        filterProducts();

        console.log("Produits chargés depuis MySQL :", products);

    } catch (error) {
        console.error("Erreur de chargement :", error);
        productsGrid.innerHTML =
            '<p class="no-products">Impossible de charger les produits.</p>';
    }
}
// 4. DISPLAY PRODUCTS
function displayProducts(list) {
    productsGrid.innerHTML = "";

    if (list.length === 0) {
        productsGrid.innerHTML =
            '<p class="no-products">Aucun produit trouvé.</p>';
        return;
    }

    list.forEach(product => {
        const card = document.createElement("article");
        card.className = "product-card";

        const image = document.createElement("img");
        image.className = "product-image";
        const imageUrl = product.image || "";

        try {
            const parsedUrl = new URL(imageUrl, window.location.origin);

            if (["http:", "https:"].includes(parsedUrl.protocol)) {
                image.src = parsedUrl.href;
            } else {
                image.src = "images/placeholder.jpg";
            }
        } catch {
            image.src = "images/placeholder.jpg";
        }

        image.alt = String(product.name || "Produit");
        image.loading = "lazy";

        const info = document.createElement("div");
        info.className = "product-info";

        const name = document.createElement("h3");
        name.textContent = product.name;

        const price = document.createElement("p");
        price.textContent = `${product.price} DH`;

        const button = document.createElement("button");
        button.className = "add-btn";
        button.textContent = "Ajouter au panier";
        button.addEventListener("click", () => addToCart(product.id));
        const detailsBtn = document.createElement("button");
        detailsBtn.className = "add-btn";
        detailsBtn.style.marginBottom = "8px";
        detailsBtn.style.background = "#b18b62";
        detailsBtn.textContent = "Voir les détails";

        detailsBtn.addEventListener("click", () => {
            openProductDetails(product.id);
        });
        info.append(name, price, detailsBtn, button);
        card.append(image, info);
        productsGrid.appendChild(card);
    });
}

// 5. ADD TO CART

function addToCart(id) {
    const product = products.find(p => p.id === id);

    if (!product) return;

    const existing = cart.find(item =>
        item.id === id &&
        !item.size &&
        !item.color
    );

    const stock = Number(product.stock);

    if (Number.isFinite(stock)) {
        const currentQuantity = existing ? existing.quantity : 0;

        if (currentQuantity >= stock) {
            alert("Quantité maximale disponible atteinte !");
            return;
        }
    }

    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            ...product,
            quantity: 1
        });
    }

    displayCart();
}


// 6. SAVE CART
function saveCart() {
    localStorage.setItem("webstart-cart", JSON.stringify(cart));
}

// 7. DISPLAY CART

function displayCart() {
    cartItems.innerHTML = "";

    let total = 0;
    let count = 0;

    cart.forEach(item => {
        total += item.price * item.quantity;
        count += item.quantity;

        const row = document.createElement("div");
        row.className = "cart-item";

        const details = document.createElement("div");

        const name = document.createElement("h4");
        name.textContent = item.name;

        const options = document.createElement("p");
        options.textContent =
            `Taille: ${item.size || "-"} | Couleur: ${item.color || "-"}`;

        const price = document.createElement("p");
        price.textContent = `${item.price} DH`;

        details.append(name, options, price);

        const controls = document.createElement("div");
        controls.className = "quantity-controls";

        const minus = document.createElement("button");
        minus.textContent = "-";
        minus.addEventListener("click", () =>
            changeQuantity(item.id, item.size, item.color, -1)
        );

        const quantity = document.createElement("span");
        quantity.textContent = item.quantity;

        const plus = document.createElement("button");
        plus.textContent = "+";
        plus.addEventListener("click", () =>
            changeQuantity(item.id, item.size, item.color, 1)
        );

        const remove = document.createElement("button");
        remove.className = "remove-btn";
        remove.textContent = "✕";
        remove.addEventListener("click", () =>
            removeFromCart(item.id, item.size, item.color)
        );

        controls.append(minus, quantity, plus, remove);
        row.append(details, controls);
        cartItems.appendChild(row);
    });

    cartCount.textContent = count;
    cartTotal.textContent = total.toFixed(2);

    if (cart.length === 0) {
        cartItems.textContent = "Votre panier est vide.";
    }

    saveCart();
}


// 8. CHANGE QUANTITY

function changeQuantity(id, size, color, amount) {
    const item = cart.find(p =>
        p.id === id &&
        p.size === size &&
        p.color === color
    );

    if (!item) return;

    if (amount > 0) {
        const product = products.find(p => p.id === id);

        if (product && product.stock !== undefined) {
            const stock = Number(product.stock);

            if (item.quantity >= stock) {
                alert("Stock insuffisant !");
                return;
            }
        }
    }

    item.quantity += amount;

    if (item.quantity <= 0) {
        cart = cart.filter(p => p !== item);
    }

    displayCart();
}
function removeFromCart(id, size, color) {
    cart = cart.filter(item =>
        !(item.id === id &&
            item.size === size &&
            item.color === color)
    );

    displayCart();
}


// 10. SEARCH AND FILTER
function filterProducts() {
    const search = searchInput.value.toLowerCase();
    const category = categoryFilter.value;

    const filtered = products.filter(product => {
        const matchesName = product.name.toLowerCase().includes(search);
        const matchesCategory =
            category === "all" || product.category === category;

        return matchesName && matchesCategory;
    });

    displayProducts(filtered);
}

searchInput.addEventListener("input", filterProducts);
categoryFilter.addEventListener("change", filterProducts);

// 11. CART OPEN / CLOSE
// CART OPEN / CLOSE
const cartOverlay = document.getElementById("cartOverlay");
const checkoutForm = document.getElementById("checkoutForm");

document.getElementById("openCart").addEventListener("click", () => {
    cartOverlay.classList.add("active");
});

function closeCart() {
    cartOverlay.classList.remove("active");
    checkoutForm.hidden = true;
}

document.getElementById("closeCart").addEventListener("click", closeCart);

cartOverlay.addEventListener("click", event => {
    if (event.target === cartOverlay) {
        closeCart();
    }
});

// 12. MOBILE MENU

const menuBtn = document.getElementById("menuBtn");
const navbarNav = document.querySelector(".navbar nav");

function closeMenu() {
    navbarNav.classList.remove("show");
    menuBtn.setAttribute("aria-expanded", "false");
}

function toggleMenu() {
    const isOpen = navbarNav.classList.toggle("show");

    menuBtn.setAttribute("aria-expanded", String(isOpen));
}

menuBtn.setAttribute("aria-expanded", "false");

menuBtn.addEventListener("click", toggleMenu);

// Close menu after clicking a navigation link
navbarNav.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", closeMenu);
});

// Close menu with Escape
document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        closeMenu();
    }
});

// Close menu when clicking outside
document.addEventListener("click", event => {
    if (
        navbarNav.classList.contains("show") &&
        !navbarNav.contains(event.target) &&
        !menuBtn.contains(event.target)
    ) {
        closeMenu();
    }
});


// 13. WHATSAPP ORDER

// 14. INITIALIZATION
loadProducts();
displayCart();


// PRODUCT DETAILS
let selectedProduct = null;
let selectedSize = "";
let selectedColor = "";

const productModal = document.getElementById("productModal");

javascript
function openProductDetails(id) {
    selectedProduct = products.find(p => Number(p.id) === Number(id));

    if (!selectedProduct) return;

    const detailImage = document.getElementById("detailImage");

    const imageUrl = selectedProduct.image || "";

    try {
        const parsedUrl = new URL(imageUrl, window.location.origin);

        if (["http:", "https:"].includes(parsedUrl.protocol)) {
            detailImage.src = parsedUrl.href;
        } else {
            detailImage.src = "images/placeholder.jpg";
        }
    } catch {
        detailImage.src = "images/placeholder.jpg";
    }

    detailImage.alt = String(selectedProduct.name || "Produit");

    document.getElementById("detailName").textContent =
        selectedProduct.name || "Produit";

    document.getElementById("detailPrice").textContent =
        Number(selectedProduct.price).toFixed(2) + " DH";

    document.getElementById("detailDescription").textContent =
        selectedProduct.description || "Aucune description disponible.";

    selectedSize = "";
    selectedColor = "";

    document.querySelectorAll(".size-btn, .color-btn")
        .forEach(btn => btn.classList.remove("selected"));

    productModal.classList.add("active");
}


// SELECT SIZE
document.querySelectorAll(".size-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        selectedSize = btn.textContent;

        document.querySelectorAll(".size-btn")
            .forEach(b => b.classList.remove("selected"));

        btn.classList.add("selected");
    });
});

// SELECT COLOR
document.querySelectorAll(".color-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        selectedColor = btn.dataset.color;

        document.querySelectorAll(".color-btn")
            .forEach(b => b.classList.remove("selected"));

        btn.classList.add("selected");
    });
});

// ADD PRODUCT WITH SIZE AND COLOR

document.getElementById("detailAddBtn")
    .addEventListener("click", () => {

        if (!selectedProduct) return;

        if (!selectedSize || !selectedColor) {
            alert("Veuillez choisir une taille et une couleur.");
            return;
        }

        const existing = cart.find(item =>
            item.id === selectedProduct.id &&
            item.size === selectedSize &&
            item.color === selectedColor
        );

        if (existing) {
            existing.quantity++;
        } else {
            cart.push({
                ...selectedProduct,
                size: selectedSize,
                color: selectedColor,
                quantity: 1
            });
        }

        displayCart();
        productModal.classList.remove("active");
    });


// CLOSE MODAL
document.getElementById("closeModal")
    .addEventListener("click", () => {
        productModal.classList.remove("active");
    });

productModal.addEventListener("click", event => {
    if (event.target === productModal) {
        productModal.classList.remove("active");
    }
});

// CHECKOUT FORM

document.getElementById("checkoutBtn").addEventListener("click", () => {
    if (cart.length === 0) {
        alert("Votre panier est vide !");
        return;
    }

    checkoutForm.hidden = false;
});

// SAVE ORDER TO DATABASE
document.getElementById("confirmOrderBtn")
    .addEventListener("click", async () => {

        const customerName =
            document.getElementById("customerName").value.trim();

        const phone =
            document.getElementById("customerPhone").value.trim();

        const address =
            document.getElementById("customerAddress").value.trim();

        const message = document.getElementById("orderMessage");
        const button = document.getElementById("confirmOrderBtn");

        if (!customerName || !phone || !address) {
            message.textContent = "Veuillez remplir tous les champs.";
            return;
        }

        if (cart.length === 0) {
            message.textContent = "Votre panier est vide.";
            return;
        }

        button.disabled = true;
        button.textContent = "Enregistrement...";

        try {
            const response = await fetch("api/orders.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    customer_name: customerName,
                    phone: phone,
                    address: address,
                    items: cart.map(item => ({
                        id: item.id,
                        quantity: item.quantity,
                        size: item.size || null,
                        color: item.color || null
                    }))
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.error || "Erreur de commande.");
            }

            message.textContent =
                `Commande #${result.order_id} enregistrée avec succès !`;

            cart = [];
            displayCart();
            checkoutForm.hidden = true;

            document.getElementById("customerName").value = "";
            document.getElementById("customerPhone").value = "";
            document.getElementById("customerAddress").value = "";

            await loadProducts();

        } catch (error) {
            message.textContent = error.message;
        } finally {
            button.disabled = false;
            button.textContent = "Confirmer la commande";
        }
    });