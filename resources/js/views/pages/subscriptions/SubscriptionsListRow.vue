<script setup>
import { ref, defineProps, reactive } from 'vue'
import DocumentLicenseIcon from "@/components/icons/DocumentLicenseIcon.vue";
import apiClientAuto from '@/utils/apiCLientAuto.js';
import handleErrors from "@/utils/handleErrors.js";

const props = defineProps({
  subscription: {
    type: Object,
    required: true,
  },
});

const token = computed(() => baseService.getTokenFromLocalStorage());
const subscription = ref(props.subscription);
const isDialogVisible = ref(false);
const deleted = ref(false);
const loading = ref(false);
const errors = ref({});

const deleteSubscription = async () => {
  loading.value = true;

  try {
    let response;
    response = await apiClientAuto.delete('/subscriptions/delete/' + subscription.value.hash);

    if (response.data.success){
      deleted.value = true;
      isDialogVisible.value = false;
    }

  } catch (error) {
    handleErrors(error);
  }

  loading.value = false;
}

</script>

<template>
  <tr v-if="!deleted">

    <td>
      <p>
        {{ subscription.name }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.company?.name }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.client.company_name }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.total }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.frequency_month }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.next_charge_date }}
      </p>
    </td>

    <td>
      <p>
        {{ subscription.expiration_date }}
      </p>
    </td>

    <td>
      <p class="text-center" v-if="subscription.email_invoice">
        <VIcon
          color="success"
          icon="tabler-mail"
        />
      </p>
    </td>

    <td>
      <p class="text-center">
        <VIcon
          v-if="subscription.charge_cc"
          color="success"
          icon="tabler-credit-card"
        />
      </p>
    </td>

    <td>

      <v-menu>
        <template v-slot:activator="{ props }">
          <v-btn-toggle
            density="compact"
            class="pa-0 pl-0 pr-0 h-auto rounded-1"
          >
            <router-link
              :rounded="0"
              :to="{ name: 'SubscriptionsEdit', params: { hash: subscription.hash } }"
            >
              <VBtn class="px-8" variant="tonal" :rounded="0"> View </VBtn>
            </router-link>

            <v-btn variant="tonal" class="btn-tableDropDownAction text-primary" v-bind="props" >
              <VIcon icon="tabler-dots-vertical"/>
            </v-btn>
          </v-btn-toggle>

        </template>

        <v-list class="border-label-info">
          <v-list-item >
            <VDialog
              v-model="isDialogVisible"
              persistent
              class="v-dialog-sm"
            >
              <!-- Dialog Activator -->
              <template #activator="{ props }">
                <v-list-item-content>
                  <v-list-item-title v-bind="props">
                    Delete
                  </v-list-item-title>
                </v-list-item-content>
              </template>

              <!-- Dialog close btn -->
              <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

              <!-- Dialog Content -->
              <VCard title="Use Google's location service?">
                <VCardText>
                  Delete {{ subscription.name }} ?
                </VCardText>

                <VCardText class="d-flex justify-end gap-3 flex-wrap">
                  <VBtn
                    color="secondary"
                    variant="tonal"
                    @click="isDialogVisible = false"
                  >
                    Disagree
                  </VBtn>
                  <VBtn
                    @click="deleteSubscription()"
                    :loading="loading"
                  >
                    Agree
                  </VBtn>
                </VCardText>
              </VCard>
            </VDialog>
          </v-list-item>
        </v-list>
      </v-menu>

    </td>

  </tr>
</template>

<style scoped lang="scss">
.v-btn--size-default {
  --v-btn-size: 0.9375rem;
  --v-btn-height: 38px;
  min-width: 20px;
  padding: 0 20px;
  font-size: 13px;
}

//.btn-dropdown {
//  padding: 5px;
//}
//
//.btn-view {
//  padding-left: 5px;
//}
</style>
