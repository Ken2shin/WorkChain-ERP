; WorkChain ERP - Assembly-Level Cryptographic Primitives (PATCHED v2.0)
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

; -----------------------------------------------------------------------------
; secure_memset - Zero memory with compiler optimization prevention
; FIXED: Now handles non-8-byte aligned sizes correctly to prevent ghost data
; rdi = pointer to memory
; rsi = size in bytes
; -----------------------------------------------------------------------------
global secure_memset
secure_memset:
    push rbx
    
    ; Check for NULL pointer
    test rdi, rdi
    jz .memset_done
    
    ; Check for size overflow (sanity check)
    cmp rsi, 0x100000000  ; 4GB max
    jg .memset_done
    
    xor rax, rax          ; Value to write (0)
    xor rcx, rcx          ; Loop counter
    
    ; --- FAST PATH: 64-bit chunks ---
    mov rdx, rsi
    and rdx, -8           ; Align size down to nearest multiple of 8
    
.memset_fast_loop:
    cmp rcx, rdx
    jge .memset_tail      ; If done with main blocks, go to tail
    
    mov qword [rdi + rcx], rax
    add rcx, 8
    
    ; Volatile barrier to prevent compiler from removing this write
    mfence 
    
    jmp .memset_fast_loop
    
    ; --- SLOW PATH: Cleanup remaining bytes ---
.memset_tail:
    cmp rcx, rsi
    jge .memset_done
    
    mov byte [rdi + rcx], al ; Clean byte by byte
    inc rcx
    jmp .memset_tail
    
.memset_done:
    pop rbx
    ret

; -----------------------------------------------------------------------------
; constant_time_compare - Constant-time memory comparison
; rdi = pointer to first buffer
; rsi = pointer to second buffer
; rdx = size in bytes
; returns: 0 if equal, non-zero if different
; -----------------------------------------------------------------------------
global constant_time_compare
constant_time_compare:
    push rbx
    
    test rdi, rdi
    jz .ctc_fail
    test rsi, rsi
    jz .ctc_fail
    
    xor rax, rax  ; Accumulator
    xor rcx, rcx  ; Counter
    
.ctc_loop:
    cmp rcx, rdx
    jge .ctc_end
    
    movzx rbx, byte [rdi + rcx]
    movzx r8, byte [rsi + rcx]
    
    xor rbx, r8
    or rax, rbx   ; Accumulate differences without branching
    
    inc rcx
    
    ; Anti-speculation barrier (lfence is lighter than mfence for reads)
    lfence
    
    jmp .ctc_loop
    
.ctc_end:
    pop rbx
    ret

.ctc_fail:
    mov rax, 1
    pop rbx
    ret

; -----------------------------------------------------------------------------
; rotate_left_32 / rotate_right_32 - Bitwise rotations
; -----------------------------------------------------------------------------
global rotate_left_32
rotate_left_32:
    mov eax, edi
    mov ecx, esi
    and ecx, 31
    rol eax, cl
    ret

global rotate_right_32
rotate_right_32:
    mov eax, edi
    mov ecx, esi
    and ecx, 31
    ror eax, cl
    ret

; -----------------------------------------------------------------------------
; xor_buffers - Fast XOR operation
; rdi = destination, rsi = src1, rdx = src2, rcx = size
; -----------------------------------------------------------------------------
global xor_buffers
xor_buffers:
    push rbx
    
    test rcx, rcx
    jz .xor_done
    
    ; Check alignment (optimization)
    mov rax, rdi
    or rax, rsi
    or rax, rdx
    test rax, 7
    jnz .xor_byte_loop ; Fallback if pointers aren't aligned
    
    ; Main loop: 64 bits at a time
    mov rax, rcx
    shr rax, 3      ; Divide by 8 (number of qwords)
    
.xor_qword_loop:
    test rax, rax
    jz .xor_tail
    
    mov r8, [rsi]
    xor r8, [rdx]
    mov [rdi], r8
    
    add rdi, 8
    add rsi, 8
    add rdx, 8
    dec rax
    jmp .xor_qword_loop
    
.xor_tail:
    mov rax, rcx
    and rax, 7      ; Get remaining bytes (size % 8)
    jz .xor_done
    
    ; Tail handling via byte loop logic below
    
.xor_byte_loop:
    cmp rcx, 0
    jz .xor_done
    
    mov al, byte [rsi]
    xor al, byte [rdx]
    mov byte [rdi], al
    
    inc rdi
    inc rsi
    inc rdx
    dec rcx
    jmp .xor_byte_loop
    
.xor_done:
    pop rbx
    ret

; -----------------------------------------------------------------------------
; Utilities: clz64, popcount64, secure_increment, flush_cache
; -----------------------------------------------------------------------------
global clz64
clz64:
    mov rax, 64
    bsr rcx, rdi
    jz .clz_ret
    sub rax, rcx
    dec rax
.clz_ret:
    ret

global popcount64
popcount64:
    popcnt rax, rdi
    ret

global secure_increment
secure_increment:
    mov rax, [rdi]
    cmp rax, 0xFFFFFFFFFFFFFFFF
    je .inc_ovf
    inc qword [rdi]
    xor eax, eax
    ret
.inc_ovf:
    mov eax, 1
    ret

global flush_cache
flush_cache:
    push rbx
    xor rcx, rcx
.flush_loop:
    cmp rcx, rsi
    jge .flush_done
    clflush [rdi + rcx]
    add rcx, CACHE_LINE_SIZE
    jmp .flush_loop
.flush_done:
    mfence
    lfence
    pop rbx
    ret

; -----------------------------------------------------------------------------
; secure_get_random_bytes - Get random bytes via RDRAND
; FIXED: Removed "multiple of 8" restriction. Can now fill any buffer size.
; -----------------------------------------------------------------------------
global secure_get_random_bytes
secure_get_random_bytes:
    push rbx
    xor rcx, rcx          ; Current offset
    
.rand_loop:
    cmp rcx, rsi
    jge .rand_success
    
    ; Generate 64 bits of entropy
    mov rbx, 10           ; Retry counter
.rand_retry:
    rdrand rax
    jc .rand_avail
    dec rbx
    jnz .rand_retry
    jmp .rand_error       ; HW RNG failure
    
.rand_avail:
    ; Determine how many bytes to write (min(8, remaining))
    mov rdx, rsi
    sub rdx, rcx          ; rdx = remaining bytes
    cmp rdx, 8
    jge .write_8
    
    ; Write remaining bytes (< 8)
    ; We have random data in RAX. Write it byte by byte.
.write_tail:
    test rdx, rdx
    jz .rand_success
    mov byte [rdi + rcx], al
    shr rax, 8            ; Shift to get next byte of entropy
    inc rcx
    dec rdx
    jmp .write_tail
    
.write_8:
    mov [rdi + rcx], rax
    add rcx, 8
    jmp .rand_loop
    
.rand_success:
    xor eax, eax
    pop rbx
    ret
    
.rand_error:
    mov eax, 1
    pop rbx
    ret

section .note.GNU-stack noalloc noexec nowrite