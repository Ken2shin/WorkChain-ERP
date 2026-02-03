; WorkChain ERP - Assembly-Level Cryptographic Primitives
; x86-64 Architecture (Intel/AMD)
; Purpose: Ultra-low-level secure operations for zero-trust enforcement

section .data
    ; Constants for AES operations
    AES_BLOCK_SIZE equ 16
    AES_KEY_SIZE_128 equ 16
    AES_KEY_SIZE_256 equ 32
    
    ; Constants for timing attack prevention
    CACHE_LINE_SIZE equ 64
    
    ; Secure memory markers
    SECURE_MEMORY_MARKER equ 0xDEADBEEFCAFEBABE
    
section .text

; secure_memset - Zero memory with compiler optimization prevention
; rdi = pointer to memory
; rsi = size in bytes
; Uses inline assembly to prevent compiler optimizations
global secure_memset
secure_memset:
    push rbx
    push r12
    
    ; Check for NULL pointer
    test rdi, rdi
    jz .memset_done
    
    ; Check for size overflow
    cmp rsi, 0x100000000  ; 4GB max
    jg .memset_done
    
    ; Zero out the memory using xor to prevent patterns
    xor rax, rax
    xor rcx, rcx
    
.memset_loop:
    cmp rcx, rsi
    jge .memset_done
    
    ; Write 64-bit values (8 bytes at a time)
    mov qword [rdi + rcx], rax
    add rcx, 8
    
    ; Use a volatile barrier to prevent compiler optimizations
    mfence
    
    jmp .memset_loop
    
.memset_done:
    pop r12
    pop rbx
    ret

; constant_time_compare - Constant-time memory comparison
; rdi = pointer to first buffer
; rsi = pointer to second buffer
; rdx = size in bytes
; returns: 0 if equal, non-zero if different (in constant time)
global constant_time_compare
constant_time_compare:
    push rbx
    
    ; Check for NULL pointers
    test rdi, rdi
    jz .ctc_not_equal
    test rsi, rsi
    jz .ctc_not_equal
    
    xor rax, rax  ; accumulator for differences
    xor rcx, rcx  ; loop counter
    
.ctc_loop:
    cmp rcx, rdx
    jge .ctc_end
    
    ; Load bytes from both buffers
    movzx rbx, byte [rdi + rcx]
    movzx r8, byte [rsi + rcx]
    
    ; XOR to find differences - still constant time
    xor rbx, r8
    
    ; Accumulate differences
    or rax, rbx
    
    ; Increment counter
    inc rcx
    
    ; Add cache line delay to prevent timing attacks
    ; This is a simple timing barrier
    mov r8, 64
.delay_loop:
    dec r8
    jnz .delay_loop
    
    jmp .ctc_loop
    
.ctc_end:
    pop rbx
    ret
    
.ctc_not_equal:
    mov rax, 1
    pop rbx
    ret

; rotate_left_32 - Constant-time 32-bit left rotation
; edi = value
; esi = rotation count
; returns: rotated value in eax
global rotate_left_32
rotate_left_32:
    mov eax, edi
    mov ecx, esi
    and ecx, 31        ; Ensure rotation count is 0-31
    rol eax, cl
    ret

; rotate_right_32 - Constant-time 32-bit right rotation
; edi = value
; esi = rotation count
; returns: rotated value in eax
global rotate_right_32
rotate_right_32:
    mov eax, edi
    mov ecx, esi
    and ecx, 31        ; Ensure rotation count is 0-31
    ror eax, cl
    ret

; xor_buffers - Fast XOR operation for encryption/decryption
; rdi = destination buffer
; rsi = source buffer 1
; rdx = source buffer 2
; rcx = size in bytes (must be multiple of 8)
global xor_buffers
xor_buffers:
    push rbx
    
    xor rax, rax
    test rcx, rcx
    jz .xor_done
    
    ; Check alignment for performance
    test rdi, 7
    jnz .xor_unaligned
    test rsi, 7
    jnz .xor_unaligned
    test rdx, 7
    jnz .xor_unaligned
    
.xor_aligned:
    ; Process 64 bytes at a time (8 x 64-bit words)
    mov rax, rcx
    shr rax, 6          ; Divide by 64
    
.xor_aligned_loop:
    test rax, rax
    jz .xor_remainder
    
    ; Load 8 64-bit words and XOR
    mov r8, [rsi]
    mov r9, [rsi + 8]
    mov r10, [rsi + 16]
    mov r11, [rsi + 24]
    
    xor r8, [rdx]
    xor r9, [rdx + 8]
    xor r10, [rdx + 16]
    xor r11, [rdx + 24]
    
    mov [rdi], r8
    mov [rdi + 8], r9
    mov [rdi + 16], r10
    mov [rdi + 24], r11
    
    ; Continue with next 32 bytes
    mov r8, [rsi + 32]
    mov r9, [rsi + 40]
    mov r10, [rsi + 48]
    mov r11, [rsi + 56]
    
    xor r8, [rdx + 32]
    xor r9, [rdx + 40]
    xor r10, [rdx + 48]
    xor r11, [rdx + 56]
    
    mov [rdi + 32], r8
    mov [rdi + 40], r9
    mov [rdi + 48], r10
    mov [rdi + 56], r11
    
    add rdi, 64
    add rsi, 64
    add rdx, 64
    dec rax
    jmp .xor_aligned_loop
    
.xor_remainder:
    mov rax, rcx
    and rax, 63         ; Remainder
    test rax, rax
    jz .xor_done
    
    ; Process remaining bytes
    xor rbx, rbx
.xor_remainder_loop:
    cmp rbx, rax
    jge .xor_done
    
    mov cl, byte [rsi + rbx]
    xor cl, byte [rdx + rbx]
    mov byte [rdi + rbx], cl
    
    inc rbx
    jmp .xor_remainder_loop
    
.xor_unaligned:
    ; Fall back to byte-by-byte operation
    xor rbx, rbx
.xor_unaligned_loop:
    cmp rbx, rcx
    jge .xor_done
    
    mov al, byte [rsi + rbx]
    xor al, byte [rdx + rbx]
    mov byte [rdi + rbx], al
    
    inc rbx
    jmp .xor_unaligned_loop
    
.xor_done:
    pop rbx
    ret

; clz64 - Count leading zeros (for constant-time bit operations)
; rdi = value
; returns: count in rax
global clz64
clz64:
    mov rax, 64
    bsr rcx, rdi
    jz .clz_return
    sub rax, rcx
    dec rax
.clz_return:
    ret

; popcount64 - Count set bits (for statistical analysis)
; rdi = value
; returns: count in rax
global popcount64
popcount64:
    popcnt rax, rdi
    ret

; secure_increment - Increment with overflow checking
; rdi = pointer to counter
; returns: 0 on success, 1 on overflow
global secure_increment
secure_increment:
    mov rax, [rdi]
    
    ; Check for overflow before incrementing
    cmp rax, 0xFFFFFFFFFFFFFFFF
    je .increment_overflow
    
    inc rax
    mov [rdi], rax
    
    xor eax, eax    ; Return 0 (success)
    ret
    
.increment_overflow:
    mov eax, 1      ; Return 1 (overflow)
    ret

; flush_cache - Flush cache lines to prevent side-channel attacks
; rdi = pointer to data
; rsi = size in bytes
global flush_cache
flush_cache:
    push rbx
    
    xor rcx, rcx
    
.flush_loop:
    cmp rcx, rsi
    jge .flush_done
    
    ; Use clflush to flush specific cache lines
    clflush [rdi + rcx]
    
    ; Move to next cache line
    add rcx, CACHE_LINE_SIZE
    
    jmp .flush_loop
    
.flush_done:
    ; Ensure flush is complete
    mfence
    lfence
    
    pop rbx
    ret

; Exports for linking
global secure_get_random_bytes

; secure_get_random_bytes - Get random bytes from RDRAND instruction
; rdi = buffer
; rsi = size in bytes (must be multiple of 8)
; returns: 0 on success, 1 on failure
global secure_get_random_bytes
secure_get_random_bytes:
    push rbx
    
    test rsi, 7
    jnz .random_error    ; Size must be multiple of 8
    
    xor rcx, rcx
    
.random_loop:
    cmp rcx, rsi
    jge .random_success
    
    ; Try to get random number using RDRAND
    rdrand rax
    jc .random_write     ; If CF=1, random number is valid
    
    ; Retry mechanism
    mov rbx, 10
.random_retry:
    rdrand rax
    jc .random_write
    dec rbx
    jnz .random_retry
    
    jmp .random_error    ; Failed to get random after retries
    
.random_write:
    mov [rdi + rcx], rax
    add rcx, 8
    jmp .random_loop
    
.random_success:
    xor eax, eax        ; Return 0
    pop rbx
    ret
    
.random_error:
    mov eax, 1          ; Return 1 (error)
    pop rbx
    ret

section .note.GNU-stack noalloc noexec nowrite
