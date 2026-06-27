import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
} from 'react';

export default forwardRef(function TextInput(
    {
        type = 'text',
        className = '',
        isFocused = false,
        ...props
    }: InputHTMLAttributes<HTMLInputElement> & { isFocused?: boolean },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            ref={localRef}
            className={
                'block w-full border-3 border-ink bg-white ' +
                'px-3 py-2 font-mono text-ink ' +
                'shadow-brutal-sm ' +
                'focus:outline-none focus:shadow-brutal ' +
                'focus:translate-x-[-1px] focus:translate-y-[-1px] ' +
                'placeholder:text-ink/40 ' +
                className
            }
        />
    );
});
