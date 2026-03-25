import { ref } from 'vue';
import api from '../plugins/axios';

export function useProducts() {
    const products = ref([]);
    const isLoading = ref(false);
    const errorMsg = ref('');

    const fetchProducts = async () => {
        isLoading.value = true;
        try {
            const response = await api.get('/products');
            products.value = response.data.data || response.data; // Adapts to paginated or standard arrays
        } catch (error) {
            errorMsg.value = 'Failed to load products.';
        } finally {
            isLoading.value = false;
        }
    };

    const createProduct = async (productData) => {
        isLoading.value = true;
        try {
            const response = await api.post('/products', productData);
            products.value.push(response.data);
            return true;
        } catch (error) {
            errorMsg.value = error.response?.data?.message || 'Failed to create product.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const updateProduct = async (id, productData) => {
        isLoading.value = true;
        try {
            const response = await api.put(`/products/${id}`, productData);
            const index = products.value.findIndex(p => p.id === id);
            if (index !== -1) {
                products.value[index] = response.data;
            }
            return true;
        } catch (error) {
            errorMsg.value = error.response?.data?.message || 'Failed to update product.';
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    const deleteProduct = async (id) => {
        try {
            await api.delete(`/products/${id}`);
            products.value = products.value.filter(p => p.id !== id);
        } catch (error) {
            errorMsg.value = 'Failed to delete product.';
        }
    };

    return {
        products,
        isLoading,
        errorMsg,
        fetchProducts,
        createProduct,
        updateProduct,
        deleteProduct
    };
}
